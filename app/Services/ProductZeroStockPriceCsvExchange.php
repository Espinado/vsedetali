<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

/**
 * Экспорт/импорт CSV: в выборку попадают товары с нулевой продажной ценой или нулевым доступным остатком на основном складе.
 * Импорт: по product_id и/или oem_code (первый OEM из product_oem_numbers), при необходимости — по sku.
 */
final class ProductZeroStockPriceCsvExchange
{
    public const PRODUCT_HEADERS = ['product_id', 'oem_code', 'sku', 'name', 'cost_price', 'price'];

    public const STOCK_HEADERS = ['quantity'];

    /**
     * Основной склад магазина (площадка): is_default или первый по id среди seller_id IS NULL.
     */
    public function defaultPlatformWarehouse(): Warehouse
    {
        $base = Warehouse::query()->platformWarehouses();

        $w = (clone $base)->where('is_default', true)->orderBy('id')->first()
            ?? $base->orderBy('id')->first();

        if (! $w) {
            throw new \RuntimeException(
                'Не найден склад площадки (запись в warehouses с seller_id = NULL). Добавьте склад (см. WarehouseSeeder).'
            );
        }

        return $w;
    }

    /**
     * Первый OEM-номер (по id записи) для выгрузки.
     */
    public function primaryOemNumber(Product $product): string
    {
        if (! $product->relationLoaded('oemNumbers')) {
            $product->load(['oemNumbers' => fn ($q) => $q->orderBy('id')]);
        }

        $first = $product->oemNumbers->first();

        return $first ? trim((string) $first->oem_number) : '';
    }

    /**
     * Товары, у которых продажная цена ≤ 0 или на основном складе площадки нет доступного остатка > 0.
     */
    public function exportQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $wid = (int) $this->defaultPlatformWarehouse()->id;

        return Product::query()
            ->where(function ($q) use ($wid) {
                $q->where('price', '<=', 0)
                    ->orWhereRaw(
                        'NOT EXISTS (
                            SELECT 1 FROM stocks
                            WHERE stocks.product_id = products.id
                            AND stocks.warehouse_id = ?
                            AND (stocks.quantity - COALESCE(stocks.reserved_quantity, 0)) > 0
                        )',
                        [$wid]
                    );
            })
            ->with([
                'stocks',
                'oemNumbers' => fn ($q) => $q->orderBy('id'),
            ])
            ->orderBy('id');
    }

    public function exportToPath(string $absolutePath): int
    {
        $warehouse = $this->defaultPlatformWarehouse();
        $warehouseId = (int) $warehouse->id;

        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть файл для записи: {$absolutePath}");
        }

        fwrite($handle, "\xEF\xBB\xBF");

        $header = array_merge(self::PRODUCT_HEADERS, self::STOCK_HEADERS);
        fputcsv($handle, $header, ',');

        $count = 0;
        foreach ($this->exportQuery()->cursor() as $product) {
            /** @var Product $product */
            $st = $product->stocks->firstWhere('warehouse_id', $warehouseId);
            $row = [
                (string) $product->id,
                $this->primaryOemNumber($product),
                (string) $product->sku,
                (string) $product->name,
                $this->formatCostForExport($product->cost_price),
                $this->formatDecimal($product->price),
                $st ? (string) (int) $st->quantity : '',
            ];
            fputcsv($handle, $row, ',');
            $count++;
        }

        fclose($handle);

        return $count;
    }

    /**
     * @return array{updated_products: int, updated_stocks: int, skipped: int, errors: list<string>}
     */
    public function importFromPath(string $absolutePath, string $delimiter, bool $dryRun): array
    {
        $warehouse = $this->defaultPlatformWarehouse();
        $warehouseId = (int) $warehouse->id;

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Не удалось открыть файл: {$absolutePath}");
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerRow = fgetcsv($handle, 0, $delimiter);
        if ($headerRow === false || $headerRow === [null] || $headerRow === []) {
            fclose($handle);
            throw new \RuntimeException('Пустой файл или нет строки заголовка.');
        }

        $headerRow = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
        $col = array_flip($headerRow);

        foreach (self::PRODUCT_HEADERS as $required) {
            if (! isset($col[$required])) {
                fclose($handle);
                throw new \RuntimeException("В заголовке нет колонки «{$required}».");
            }
        }

        foreach (self::STOCK_HEADERS as $required) {
            if (! isset($col[$required])) {
                fclose($handle);
                throw new \RuntimeException('В заголовке нет колонки «quantity».');
            }
        }

        $stats = [
            'updated_products' => 0,
            'updated_stocks' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $line = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            if ($this->csvRowEmpty($row)) {
                continue;
            }

            $product = $this->resolveProductForImport($row, $col, $line, $stats);
            if ($product === null) {
                continue;
            }

            $fileSku = trim((string) ($row[$col['sku']] ?? ''));
            if ($fileSku !== '' && $fileSku !== (string) $product->sku) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$line}: sku в файле «{$fileSku}» не совпадает с БД «{$product->sku}» (товар id={$product->id}).";

                continue;
            }

            $costRaw = trim((string) ($row[$col['cost_price']] ?? ''));
            $costUpdate = null;
            $costExplicit = false;
            if ($costRaw !== '') {
                $normalized = $this->normalizeNumberString($costRaw);
                if (! is_numeric($normalized)) {
                    $stats['skipped']++;
                    $stats['errors'][] = "Строка {$line}: нечисловая себестоимость «{$costRaw}».";

                    continue;
                }
                $costUpdate = round((float) $normalized, 2);
                $costExplicit = true;
            }

            $priceRaw = trim((string) ($row[$col['price']] ?? ''));
            $priceUpdate = null;
            if ($priceRaw !== '') {
                $normalized = $this->normalizeNumberString($priceRaw);
                if (! is_numeric($normalized)) {
                    $stats['skipped']++;
                    $stats['errors'][] = "Строка {$line}: нечисловая цена продажи «{$priceRaw}».";

                    continue;
                }
                $priceUpdate = round((float) $normalized, 2);
            }

            $qCell = trim((string) ($row[$col['quantity']] ?? ''));
            $stockOp = null;
            if ($qCell !== '') {
                $qty = max(0, (int) $qCell);
                $stockOp = ['warehouse_id' => $warehouseId, 'quantity' => $qty];
            }

            if ($priceUpdate === null && ! $costExplicit && $stockOp === null) {
                $stats['skipped']++;

                continue;
            }

            if ($dryRun) {
                if ($priceUpdate !== null || $costExplicit) {
                    $stats['updated_products']++;
                }
                if ($stockOp !== null) {
                    $stats['updated_stocks']++;
                }

                continue;
            }

            DB::transaction(function () use ($product, $priceUpdate, $costExplicit, $costUpdate, $stockOp, &$stats) {
                $payload = [];
                if ($priceUpdate !== null) {
                    $payload['price'] = $priceUpdate;
                }
                if ($costExplicit) {
                    $payload['cost_price'] = $costUpdate;
                }
                if ($payload !== []) {
                    $product->update($payload);
                    $stats['updated_products']++;
                }
                if ($stockOp !== null) {
                    $stock = Stock::query()->firstOrNew([
                        'product_id' => $product->id,
                        'warehouse_id' => $stockOp['warehouse_id'],
                    ]);
                    $stock->quantity = $stockOp['quantity'];
                    if (! $stock->exists) {
                        $stock->reserved_quantity = 0;
                    }
                    $stock->save();
                    $stats['updated_stocks']++;
                }
            });
        }

        fclose($handle);

        return $stats;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $col
     * @param  array{skipped: int, errors: list<string>}  $stats
     */
    private function resolveProductForImport(array $row, array $col, int $line, array &$stats): ?Product
    {
        $pidRaw = trim((string) ($row[$col['product_id']] ?? ''));
        $oemRaw = trim((string) ($row[$col['oem_code']] ?? ''));
        $skuRaw = trim((string) ($row[$col['sku']] ?? ''));

        if ($pidRaw !== '' && ctype_digit($pidRaw) && (int) $pidRaw > 0) {
            $product = Product::query()->find((int) $pidRaw);
            if (! $product) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$line}: товар product_id={$pidRaw} не найден.";

                return null;
            }
            if ($oemRaw !== '' && ! $this->productHasOemNumber($product, $oemRaw)) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$line}: OEM «{$oemRaw}» не относится к товару id={$product->id} (проверьте строку).";

                return null;
            }

            return $product;
        }

        if ($oemRaw !== '') {
            $byOem = Product::query()
                ->whereHas('oemNumbers', fn ($q) => $q->where('oem_number', $oemRaw))
                ->get();

            if ($byOem->count() === 1) {
                return $byOem->first();
            }
            if ($byOem->count() > 1) {
                $stats['skipped']++;
                $stats['errors'][] = "Строка {$line}: OEM «{$oemRaw}» совпадает с несколькими товарами — укажите product_id.";

                return null;
            }

            $bySkuExact = Product::query()->where('sku', $oemRaw)->first();
            if ($bySkuExact !== null) {
                return $bySkuExact;
            }

            $bySkuLoose = Product::query()->whereSkuMatchesPartNumber($oemRaw)->first();
            if ($bySkuLoose !== null) {
                return $bySkuLoose;
            }

            $stats['skipped']++;
            $stats['errors'][] = "Строка {$line}: товар с OEM «{$oemRaw}» не найден (и нет точного/нормализованного совпадения по sku).";

            return null;
        }

        if ($skuRaw !== '') {
            $product = Product::query()->where('sku', $skuRaw)->first();
            if ($product !== null) {
                return $product;
            }
            $product = Product::query()->whereSkuMatchesPartNumber($skuRaw)->first();
            if ($product !== null) {
                return $product;
            }
            $stats['skipped']++;
            $stats['errors'][] = "Строка {$line}: товар со sku «{$skuRaw}» не найден.";

            return null;
        }

        $stats['skipped']++;
        $stats['errors'][] = "Строка {$line}: укажите product_id или oem_code (или sku).";

        return null;
    }

    private function productHasOemNumber(Product $product, string $oem): bool
    {
        $oem = trim($oem);

        return $product->oemNumbers()->where('oem_number', $oem)->exists();
    }

    private function normalizeNumberString(string $raw): string
    {
        return str_replace([' ', ','], ['', '.'], $raw);
    }

    private function formatDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function formatCostForExport(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param list<string|null>|false $row
     */
    private function csvRowEmpty(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
