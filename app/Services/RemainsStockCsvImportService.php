<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Импорт CSV отчёта «Остатки» (колонки: Код, Артикул, Наименование, …, Остаток, Себестоимость, …, Цена продажи, …, Дней на складе).
 */
class RemainsStockCsvImportService
{
    /**
     * @return array{rows: int, skipped: int, imported: int, updated_products: int, created_products: int}
     */
    public function import(string $absolutePath, bool $dryRun = false): array
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("Файл недоступен: {$absolutePath}");
        }

        $stats = [
            'rows' => 0,
            'skipped' => 0,
            'imported' => 0,
            'updated_products' => 0,
            'created_products' => 0,
        ];

        $warehouse = null;
        $importCategory = null;

        if (! $dryRun) {
            $importCategory = Category::query()->firstOrCreate(
                ['slug' => 'import-ostatki-csv'],
                [
                    'name' => 'Импорт остатков (CSV)',
                    'parent_id' => null,
                    'sort' => 9998,
                    'is_active' => true,
                ]
            );

            $warehouse = Warehouse::query()->where('is_default', true)->first()
                ?? Warehouse::query()->where('is_active', true)->first()
                ?? Warehouse::query()->first();

            if (! $warehouse) {
                $warehouse = Warehouse::query()->create([
                    'name' => 'Основной склад',
                    'code' => 'MAIN',
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть файл.');
        }

        $headerPassed = false;
        $currentBrand = null;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $stats['rows']++;

                $row = array_map(fn ($c) => $this->normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF")), $row);

                if (! $headerPassed) {
                    if (($row[1] ?? '') === 'Код' && ($row[2] ?? '') === 'Артикул') {
                        $headerPassed = true;
                    }

                    continue;
                }

                $code = $row[1] ?? '';
                $skuRaw = $row[2] ?? '';
                $name = $row[3] ?? '';

                if ($skuRaw === '' && $name === '' && $code !== '' && ! is_numeric(str_replace([' ', "\t"], '', $code))) {
                    if (! $dryRun) {
                        $currentBrand = Brand::query()->firstOrCreate(
                            ['slug' => Str::slug($code) ?: Str::random(8)],
                            ['name' => Str::limit($code, 255, ''), 'is_active' => true]
                        );
                    }
                    $stats['skipped']++;

                    continue;
                }

                if ($skuRaw === '' || $name === '') {
                    $stats['skipped']++;

                    continue;
                }

                $sku = mb_strlen($skuRaw) <= 100
                    ? $skuRaw
                    : (mb_substr($skuRaw, 0, 90).'_'.substr(md5($skuRaw), 0, 8));

                $reserved = (int) round($this->parseDecimal($row[6] ?? '0'));
                $quantity = (int) round($this->parseDecimal($row[8] ?? '0'));
                $costPrice = $this->parseDecimal($row[9] ?? null);
                $salePrice = $this->parseDecimal($row[11] ?? null);
                $days = isset($row[13]) && $row[13] !== ''
                    ? (int) round($this->parseDecimal($row[13]))
                    : null;

                if ($dryRun) {
                    $stats['imported']++;

                    continue;
                }

                DB::transaction(function () use (
                    &$stats,
                    $importCategory,
                    $warehouse,
                    $sku,
                    $code,
                    $name,
                    $quantity,
                    $reserved,
                    $costPrice,
                    $salePrice,
                    $days,
                    $currentBrand
                ) {
                    $brandId = $currentBrand?->id;

                    $product = Product::query()->where('sku', $sku)->first();

                    if ($product === null) {
                        $product = new Product;
                        $product->sku = $sku;
                        $product->slug = $this->uniqueProductSlug($sku);
                        $product->category_id = $importCategory->id;
                        $product->brand_id = $brandId;
                        $product->is_active = true;
                        $product->type = 'part';
                        $stats['created_products']++;
                    } else {
                        $stats['updated_products']++;
                    }

                    $product->code = $code !== '' ? Str::limit($code, 50, '') : null;
                    $product->name = Str::limit($name, 500, '');
                    $product->brand_id = $brandId ?? $product->brand_id;
                    if ($costPrice !== null) {
                        $product->cost_price = $costPrice;
                    }
                    if ($salePrice !== null) {
                        $product->price = $salePrice;
                    }
                    if ($product->category_id === null) {
                        $product->category_id = $importCategory->id;
                    }
                    $product->save();

                    Stock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => max(0, $quantity),
                            'reserved_quantity' => max(0, $reserved),
                            'days_in_warehouse' => $days,
                        ]
                    );
                });

                $stats['imported']++;
            }
        } finally {
            fclose($handle);
        }

        if (! $headerPassed) {
            throw new \RuntimeException('В файле не найдена строка заголовка с колонками «Код» и «Артикул». Проверьте формат CSV (UTF-8).');
        }

        return $stats;
    }

    private function parseDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d.\-]/', '', $normalized);

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        return (float) $normalized;
    }

    private function uniqueProductSlug(string $sku): string
    {
        $base = Str::slug(Str::limit($sku, 80, ''));
        if ($base === '') {
            $base = 'p-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $n = 0;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return Str::limit($slug, 500, '');
    }

    private function normalizeUtf8String(string $s): string
    {
        if ($s === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($clean !== false) {
                $s = $clean;
            }
        }

        if (! mb_check_encoding($s, 'UTF-8')) {
            $converted = @mb_convert_encoding($s, 'UTF-8', 'Windows-1251');
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $s;
    }
}
