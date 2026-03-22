<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeelyBambooImportService
{
    /** @var array{rows: int, skipped: int, imported: int, created_products: int, updated_products: int, created_vehicles: int, attached_vehicles: int} */
    public function import(string $absolutePath, bool $dryRun = false): array
    {
        if (! is_readable($absolutePath)) {
            throw new \InvalidArgumentException("Файл недоступен для чтения: {$absolutePath}");
        }

        $skipFrom = (int) config('geely_bamboo_import.skip_line_from', 1);
        $skipTo = (int) config('geely_bamboo_import.skip_line_to', 3);
        $skipSet = array_flip(array_map('intval', config('geely_bamboo_import.skip_lines', [])));

        $stats = [
            'rows' => 0,
            'skipped' => 0,
            'imported' => 0,
            'created_products' => 0,
            'updated_products' => 0,
            'created_vehicles' => 0,
            'attached_vehicles' => 0,
        ];

        $warehouse = null;
        $importCategory = null;
        if (! $dryRun) {
            $importCategory = Category::query()->firstOrCreate(
                ['slug' => 'import-geely-bamboo'],
                [
                    'name' => 'Импорт Geely Бамбук',
                    'parent_id' => null,
                    'sort' => 9999,
                    'is_active' => true,
                ]
            );

            $warehouse = Warehouse::query()->where('is_default', true)->first()
                ?? Warehouse::query()->where('is_active', true)->first()
                ?? Warehouse::query()->first();

            if (! $warehouse) {
                $warehouse = Warehouse::query()->create([
                    'name' => 'Импорт',
                    'code' => 'IMPORT',
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть файл.');
        }

        $lineNumber = 0;
        $lastMake = null;
        $lastModel = null;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                $stats['rows']++;

                if ($lineNumber >= $skipFrom && $lineNumber <= $skipTo) {
                    $stats['skipped']++;

                    continue;
                }

                if (isset($skipSet[$lineNumber])) {
                    $stats['skipped']++;

                    continue;
                }

                $row = array_map(function ($cell) {
                    if ($cell === null) {
                        return '';
                    }

                    $s = trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF");

                    return $this->normalizeUtf8String($s);
                }, $row);

                $row = array_pad($row, 6, '');

                $vehicleCell = $row[0];
                $name = $row[1];
                $oem = $row[2];
                $qtyRaw = $row[3];

                if ($vehicleCell !== '') {
                    $tokens = preg_split('/\s+/u', $vehicleCell, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if (count($tokens) >= 2) {
                        $lastMake = $tokens[0];
                        $lastModel = $tokens[1];
                    } elseif (count($tokens) === 1) {
                        $lastMake = $tokens[0];
                        $lastModel = null;
                    }
                }

                if ($oem === '' || $name === '') {
                    $stats['skipped']++;

                    continue;
                }

                if ($lastMake === null || $lastModel === null) {
                    $stats['skipped']++;

                    continue;
                }

                $sku = mb_strlen($oem) <= 100
                    ? $oem
                    : (mb_substr($oem, 0, 90).'_'.substr(md5($oem), 0, 8));
                $quantity = max(0, (int) preg_replace('/[^0-9\-]/', '', (string) $qtyRaw));

                if ($dryRun) {
                    $stats['imported']++;

                    continue;
                }

                DB::transaction(function () use (
                    &$stats,
                    $sku,
                    $name,
                    $lastMake,
                    $lastModel,
                    $quantity,
                    $warehouse,
                    $importCategory,
                    $oem
                ) {
                    $brand = Brand::query()->firstOrCreate(
                        ['slug' => Str::slug($lastMake) ?: Str::slug($lastMake.'-make')],
                        ['name' => $lastMake, 'is_active' => true]
                    );

                    $vehicle = Vehicle::query()->firstOrCreate(
                        [
                            'make' => $lastMake,
                            'model' => $lastModel,
                            'generation' => null,
                        ],
                        [
                            'year_from' => null,
                            'year_to' => null,
                            'engine' => null,
                            'body_type' => null,
                        ]
                    );

                    if ($vehicle->wasRecentlyCreated) {
                        $stats['created_vehicles']++;
                    }

                    $product = Product::query()->where('sku', $sku)->first();

                    if ($product === null) {
                        $product = new Product;
                        $product->sku = $sku;
                        $product->slug = $this->uniqueProductSlug($sku);
                        $product->category_id = $importCategory->id;
                        $product->brand_id = $brand->id;
                        $product->price = 0;
                        $product->is_active = true;
                        $product->type = 'part';
                        $stats['created_products']++;
                    } else {
                        $stats['updated_products']++;
                        if ($product->category_id === null) {
                            $product->category_id = $importCategory->id;
                        }
                    }

                    $product->name = $name;
                    $product->brand_id = $brand->id;
                    $product->save();

                    $oemForPivot = Str::limit(explode('/', $oem)[0], 100, '');

                    if (! $product->vehicles()->where('vehicles.id', $vehicle->id)->exists()) {
                        $product->vehicles()->attach($vehicle->id, ['oem_number' => $oemForPivot ?: null]);
                        $stats['attached_vehicles']++;
                    }

                    Stock::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                        ],
                        [
                            'quantity' => $quantity,
                            'reserved_quantity' => 0,
                        ]
                    );
                });

                $stats['imported']++;
            }
        } finally {
            fclose($handle);
        }

        return $stats;
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

    /**
     * Убирает битые UTF-8 байты (частая причина MySQL 1366) и пробует Windows-1251.
     */
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
