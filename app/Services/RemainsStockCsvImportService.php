<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\VehicleLabelNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function __construct(
        protected CatalogProductImageDownloader $catalogProductImageDownloader,
        protected CatalogVehicleSyncService $catalogVehicleSyncService,
        protected CatalogProductMetadataSyncService $catalogProductMetadataSync,
        protected AutoPartsCatalogService $autoPartsCatalogService,
    ) {}

    /**
     * @return array{
     *   rows: int,
     *   skipped: int,
     *   imported: int,
     *   created_products: int,
     *   skipped_existing: int,
     *   created_vehicles: int,
     *   attached_vehicles: int,
     *   catalog_images_attached: int,
     *   catalog_images_failed: int,
     *   catalog_images_no_url: int,
     *   catalog_images_no_api: int,
     *   catalog_vehicles_attached: int,
     *   catalog_enrichment_failed: int,
     *   catalog_primary_oem_added: int,
     *   catalog_metadata_oem_extra: int,
     *   catalog_metadata_cross: int,
     *   catalog_metadata_attributes: int,
     *   catalog_storefront_categories: int,
     * }
     */
    /**
     * @param  'utf-8'|'cp1251'|null  $csvEncoding  см. {@see RemainsStockCsvReader::readHeaderRow()}
     */
    public function import(string $absolutePath, bool $dryRun = false, ?bool $downloadCatalogImages = null, ?string $csvEncoding = null): array
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
            'catalog_images_attached' => 0,
            'catalog_images_failed' => 0,
            'catalog_images_no_url' => 0,
            'catalog_images_no_api' => 0,
            'catalog_vehicles_attached' => 0,
            'catalog_enrichment_failed' => 0,
            'catalog_primary_oem_added' => 0,
            'catalog_metadata_oem_extra' => 0,
            'catalog_metadata_cross' => 0,
            'catalog_metadata_attributes' => 0,
            'catalog_storefront_categories' => 0,
        ];

        $downloadImages = $downloadCatalogImages ?? (bool) config('remains_stock_import.download_catalog_images', true);
        $syncCatalogVehicles = (bool) config('remains_stock_import.sync_catalog_vehicles', false);
        if ($dryRun) {
            $downloadImages = false;
            $syncCatalogVehicles = false;
        }

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

        foreach (RemainsStockCsvReader::iterateDataRows($absolutePath, $csvEncoding) as $row) {
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

            $createdProduct = null;

            DB::transaction(function () use (
                &$stats,
                &$createdProduct,
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

                if ($this->catalogProductMetadataSync->ensurePrimaryOemFromSku($product)) {
                    $stats['catalog_primary_oem_added']++;
                }

                foreach ($this->context['vehicles'] as $vm) {
                    $modelRaw = trim((string) ($vm['model'] ?? ''));
                    $defaultModel = (string) config('remains_stock_import.default_model_when_missing', 'Общее');
                    $modelVal = $modelRaw !== '' ? $modelRaw : $defaultModel;
                    $vehicle = Vehicle::query()->firstOrCreate(
                        [
                            'make' => $vm['make'],
                            'model' => $modelVal,
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

                $createdProduct = $product;
            });

            if ($createdProduct !== null) {
                $codeForCatalog = trim($code) !== '' ? trim($code) : null;

                $ranCatalogExtra = false;

                if ($syncCatalogVehicles && $this->autoPartsCatalogService->isConfigured()) {
                    $ranCatalogExtra = true;
                    try {
                        $enriched = $this->autoPartsCatalogService->lookupEnrichedForStockWithCandidates($sku, $codeForCatalog);
                        $stats['catalog_vehicles_attached'] += $this->catalogVehicleSyncService->attachFromEnrichment(
                            $createdProduct->fresh(),
                            $enriched
                        );

                        $meta = $this->catalogProductMetadataSync->syncFromEnrichment($createdProduct->fresh(), $enriched);
                        $stats['catalog_metadata_oem_extra'] += $meta['oem_added'];
                        $stats['catalog_metadata_cross'] += $meta['cross_added'];
                        $stats['catalog_metadata_attributes'] += $meta['attributes_upserted'];

                        if ($this->catalogProductMetadataSync->assignStorefrontCategoryFromEnrichment($createdProduct->fresh(), $enriched)) {
                            $stats['catalog_storefront_categories']++;
                        }

                        if ($downloadImages && ! $createdProduct->fresh()->images()->exists()) {
                            $url = trim((string) ($enriched['catalog_image_url'] ?? ''));
                            if ($url !== '') {
                                if ($this->catalogProductImageDownloader->downloadAndAttach($createdProduct->fresh(), $url)) {
                                    $stats['catalog_images_attached']++;
                                } else {
                                    $stats['catalog_images_failed']++;
                                }
                            } else {
                                $stats['catalog_images_no_url']++;
                            }
                        }
                    } catch (\Throwable $e) {
                        $stats['catalog_enrichment_failed']++;
                        Log::warning('remains_import_catalog_enrichment', [
                            'sku' => $sku,
                            'product_id' => $createdProduct->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                } elseif ($downloadImages) {
                    $ranCatalogExtra = true;
                    try {
                        $imgResult = $this->catalogProductImageDownloader->attachFromSkuRawIfConfigured(
                            $createdProduct,
                            $sku,
                            $codeForCatalog
                        );
                    } catch (\Throwable $e) {
                        $stats['catalog_images_failed']++;
                        Log::warning('remains_import_catalog_image', [
                            'sku' => $sku,
                            'product_id' => $createdProduct->id,
                            'message' => $e->getMessage(),
                        ]);
                        $imgResult = null;
                    }

                    if (isset($imgResult)) {
                        match ($imgResult) {
                            'attached' => $stats['catalog_images_attached']++,
                            'download_failed', 'api_error' => $stats['catalog_images_failed']++,
                            'no_url' => $stats['catalog_images_no_url']++,
                            'no_api' => $stats['catalog_images_no_api']++,
                            'has_images' => null,
                        };
                    }
                }

                if ($ranCatalogExtra) {
                    $sleepMs = max(0, (int) config('remains_stock_import.catalog_image_sleep_ms', 80));
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }
                }
            }
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
