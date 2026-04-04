<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Импорт CSV отчёта «Остатки» с секциями (Код, Артикул, Наименование, Доступно, Себестоимость, Цена продажи, …).
 *
 * Правила:
 * - Существующий SKU: строка пропускается целиком (без обновления).
 * - Новые товары: category_id = null.
 * - Секции «Б/У Дефект» и «Запчасти и аксессуары»: товары без привязки к марке/модели/бренду.
 */
class RemainsStockCsvImportService
{
    /** @var array{standalone: bool, part_brand_id: ?int, vehicles: list<array{make: string, model: ?string}>} */
    private array $context;

    /**
     * @return array{
     *   rows: int,
     *   skipped: int,
     *   imported: int,
     *   created_products: int,
     *   skipped_existing: int,
     *   created_vehicles: int,
     *   attached_vehicles: int
     * }
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
            'created_products' => 0,
            'skipped_existing' => 0,
            'created_vehicles' => 0,
            'attached_vehicles' => 0,
        ];

        $this->context = [
            'standalone' => true,
            'part_brand_id' => null,
            'vehicles' => [],
        ];

        $warehouse = null;

        if (! $dryRun) {
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

        foreach (RemainsStockCsvReader::iterateDataRows($absolutePath) as $row) {
            $stats['rows']++;

            $row = array_pad($row, 16, '');

            $code = $row[1] ?? '';
            $skuRaw = $row[2] ?? '';
            $name = $row[3] ?? '';

            if ($this->isSectionHeaderRow($skuRaw, $name, $code)) {
                $this->context = $this->parseSectionLabel($code, $dryRun);

                continue;
            }

            if ($skuRaw === '' || $name === '') {
                $stats['skipped']++;

                continue;
            }

            $sku = mb_strlen($skuRaw) <= 100
                ? $skuRaw
                : (mb_substr($skuRaw, 0, 90).'_'.substr(md5($skuRaw), 0, 8));

            $available = (int) round($this->parseDecimal($row[5] ?? '0') ?? 0);
            $reserved = (int) round($this->parseDecimal($row[6] ?? '0') ?? 0);
            $costPrice = $this->parseDecimal($row[9] ?? null);
            $salePrice = $this->parseDecimal($row[11] ?? null);
            $days = isset($row[13]) && $row[13] !== ''
                ? (int) round($this->parseDecimal($row[13]) ?? 0)
                : null;

            if ($dryRun) {
                if (Product::query()->where('sku', $sku)->exists()) {
                    $stats['skipped_existing']++;
                } else {
                    $stats['imported']++;
                }

                continue;
            }

            DB::transaction(function () use (
                &$stats,
                $warehouse,
                $sku,
                $code,
                $name,
                $available,
                $reserved,
                $costPrice,
                $salePrice,
                $days
            ) {
                if (Product::query()->where('sku', $sku)->exists()) {
                    $stats['skipped_existing']++;

                    return;
                }

                $product = new Product;
                $product->sku = $sku;
                $product->slug = $this->uniqueProductSlug($sku);
                $product->category_id = null;
                $product->is_active = true;
                $product->type = 'part';
                $product->code = $code !== '' ? Str::limit($code, 50, '') : null;
                $product->name = Str::limit($name, 500, '');
                if ($costPrice !== null) {
                    $product->cost_price = $costPrice;
                }
                if ($salePrice !== null) {
                    $product->price = $salePrice;
                }

                if ($this->context['standalone']) {
                    $product->brand_id = null;
                } elseif ($this->context['part_brand_id'] !== null) {
                    $product->brand_id = $this->context['part_brand_id'];
                } else {
                    $product->brand_id = null;
                }

                $product->save();
                $stats['created_products']++;
                $stats['imported']++;

                foreach ($this->context['vehicles'] as $vm) {
                    $modelVal = $vm['model'] ?? '';
                    $vehicle = Vehicle::query()->firstOrCreate(
                        [
                            'make' => $vm['make'],
                            'model' => $modelVal !== '' ? $modelVal : '',
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

                    $oem = Str::limit(explode('/', $sku)[0], 100, '');
                    if (! $product->vehicles()->where('vehicles.id', $vehicle->id)->exists()) {
                        $product->vehicles()->attach($vehicle->id, ['oem_number' => $oem ?: null]);
                        $stats['attached_vehicles']++;
                    }
                }

                Stock::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouse->id,
                    ],
                    [
                        'quantity' => max(0, $available),
                        'reserved_quantity' => max(0, $reserved),
                        'days_in_warehouse' => $days,
                    ]
                );
            });
        }

        return $stats;
    }

    private function isSectionHeaderRow(string $skuRaw, string $name, string $code): bool
    {
        return $skuRaw === '' && $name === '' && trim($code) !== '';
    }

    /**
     * @return array{standalone: bool, part_brand_id: ?int, vehicles: list<array{make: string, model: ?string}>}
     */
    private function parseSectionLabel(string $code, bool $dryRun): array
    {
        $code = trim($code);
        $standalone = config('remains_stock_import.standalone_section_labels', []);

        foreach ($standalone as $label) {
            if ($code === $label) {
                return [
                    'standalone' => true,
                    'part_brand_id' => null,
                    'vehicles' => [],
                ];
            }
        }

        if ($code === 'DSLK') {
            $cfg = config('remains_stock_import.dslk_brand', ['name' => 'DI-SOLIK', 'slug' => 'di-solik']);
            $brand = null;
            if (! $dryRun) {
                $brand = Brand::query()->firstOrCreate(
                    ['slug' => $cfg['slug']],
                    ['name' => $cfg['name'], 'is_active' => true]
                );
            }

            return [
                'standalone' => false,
                'part_brand_id' => $brand?->id,
                'vehicles' => [],
            ];
        }

        if (str_starts_with($code, 'Производители/')) {
            $raw = trim(substr($code, strlen('Производители/')));
            $brandName = $raw !== '' ? $raw : 'Unknown';
            $brand = null;
            if (! $dryRun) {
                $slug = Str::slug($brandName) ?: 'brand-'.Str::lower(Str::random(6));
                $brand = Brand::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => Str::limit($brandName, 255, ''), 'is_active' => true]
                );
            }

            return [
                'standalone' => false,
                'part_brand_id' => $brand?->id,
                'vehicles' => [],
            ];
        }

        if (str_starts_with($code, 'Марки/')) {
            $path = trim(substr($code, strlen('Марки/')));
            if ($path === '') {
                return [
                    'standalone' => true,
                    'part_brand_id' => null,
                    'vehicles' => [],
                ];
            }

            $segments = array_values(array_filter(array_map('trim', explode('/', $path)), fn ($s) => $s !== ''));

            $multi = config('remains_stock_import.multi_make_segments', []);

            if (count($segments) === 1 && isset($multi[$segments[0]])) {
                $vehicles = [];
                foreach ($multi[$segments[0]] as $makeName) {
                    $vehicles[] = [
                        'make' => VehicleLabelNormalizer::title($makeName),
                        'model' => null,
                    ];
                }

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => $vehicles,
                ];
            }

            if (count($segments) >= 2) {
                $make = VehicleLabelNormalizer::title($segments[0]);
                $rest = array_slice($segments, 1);
                $modelLabel = VehicleLabelNormalizer::title(implode(' ', $rest));

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => [['make' => $make, 'model' => $modelLabel]],
                ];
            }

            if (count($segments) === 1) {
                $make = VehicleLabelNormalizer::title($segments[0]);

                return [
                    'standalone' => false,
                    'part_brand_id' => null,
                    'vehicles' => [['make' => $make, 'model' => null]],
                ];
            }
        }

        // HongQi / HongQi H5 / без слэшей
        $parts = preg_split('/\s+/u', $code, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) >= 1) {
            $make = VehicleLabelNormalizer::title($parts[0]);
            $model = count($parts) >= 2
                ? VehicleLabelNormalizer::title(implode(' ', array_slice($parts, 1)))
                : null;

            return [
                'standalone' => false,
                'part_brand_id' => null,
                'vehicles' => [['make' => $make, 'model' => $model]],
            ];
        }

        return [
            'standalone' => true,
            'part_brand_id' => null,
            'vehicles' => [],
        ];
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
}
