<?php

namespace App\Services;

use App\Console\Commands\ExportRemainsCsvOemBundlesCommand;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\ProductCatalogSlug;
use App\Support\RemainsOemBundleJsonlRow;
use App\Support\StorefrontVehicleProductNameConsistency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Импорт CSV отчёта «Остатки» с секциями (Код, Артикул, Наименование, …, Остаток, Себестоимость, …).
 *
 * Количество на складе (stocks.quantity) берётся из колонки «Остаток» (типичный порядок 1С при ведущей
 * пустой колонке: индекс 8; не путать с «Доступно» на индексе 5).
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
        protected RemainsStockCsvSectionContextParser $sectionContextParser,
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

            if (RemainsStockCsvReader::isSectionHeaderRow($skuRaw, $name, $code)) {
                $this->context = $this->sectionContextParser->parse($code, $dryRun);

                continue;
            }

            if ($skuRaw === '' || $name === '') {
                $stats['skipped']++;

                continue;
            }

            $sku = mb_strlen($skuRaw) <= 100
                ? $skuRaw
                : (mb_substr($skuRaw, 0, 90).'_'.substr(md5($skuRaw), 0, 8));

            // 5 Доступно, 6 Резерв, 7 Ожидание, 8 Остаток — наличие в stocks.quantity = «Остаток»
            $quantity = $this->parseStocksUnsignedIntColumn($row[8] ?? '0', 'Остаток', $sku);
            $reserved = $this->parseStocksUnsignedIntColumn($row[6] ?? '0', 'Резерв', $sku);
            $costPrice = $this->parseDecimal($row[9] ?? null);
            $salePrice = $this->parseDecimal($row[11] ?? null);
            $days = isset($row[13]) && $row[13] !== ''
                ? $this->parseDaysInWarehouseColumn($row[13], $sku)
                : null;

            if ($dryRun) {
                if (Product::query()->where('sku', $sku)->exists()) {
                    $stats['skipped_existing']++;
                } else {
                    $stats['imported']++;
                }

                continue;
            }

            $createdProduct = $this->insertNewRemainsProductIfNotExists(
                $sku,
                $code,
                $name,
                $quantity,
                $reserved,
                $costPrice,
                $salePrice,
                $days,
                $warehouse,
                $this->context,
                $stats
            );

            if ($createdProduct !== null) {
                $codeForCatalog = trim($code) !== '' ? trim($code) : null;

                $ranCatalogExtra = false;

                if ($syncCatalogVehicles && $this->autoPartsCatalogService->isConfigured()) {
                    $ranCatalogExtra = true;
                    try {
                        $enriched = $this->autoPartsCatalogService->lookupEnrichedForStockWithCandidates($sku, $codeForCatalog, $name);
                        $this->applyEnrichmentFromLookupArray(
                            $createdProduct->fresh(),
                            $enriched,
                            $downloadImages,
                            $stats
                        );
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

    /**
     * Проверка одной строки JSONL без записи в БД (для {@see \App\Console\Commands\CatalogVerifyOemBundlesJsonlCommand}).
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   ok: bool,
     *   error: string|null,
     *   sku: string,
     *   name: string,
     *   import_context_vehicle_make: string|null,
     *   name_lead_make_conflicts_context: bool,
     *   catalog_compat_vehicle_rows: int,
     * }
     */
    public function analyzeOemBundleJsonlPayload(array $payload): array
    {
        $out = [
            'ok' => false,
            'error' => null,
            'sku' => '',
            'name' => '',
            'import_context_vehicle_make' => null,
            'name_lead_make_conflicts_context' => false,
            'catalog_compat_vehicle_rows' => 0,
        ];

        $catalog = $payload['catalog'] ?? null;
        if (! is_array($catalog)) {
            $out['error'] = 'missing_catalog';

            return $out;
        }

        $enriched = $this->autoPartsCatalogService->enrichmentPayloadFromFullOemBundle($catalog);
        if (($enriched['source'] ?? 'none') === 'none') {
            $out['error'] = 'bundle_not_oem';

            return $out;
        }

        $compat = $catalog['compatibility'] ?? null;
        if (is_array($compat) && isset($compat['vehicles']) && is_array($compat['vehicles'])) {
            foreach ($compat['vehicles'] as $v) {
                if (! is_array($v)) {
                    continue;
                }
                $mk = trim((string) ($v['make'] ?? ''));
                $md = trim((string) ($v['model'] ?? ''));
                if ($mk !== '' && $md !== '') {
                    $out['catalog_compat_vehicle_rows']++;
                }
            }
        }

        if (isset($payload['csv_row']) && is_array($payload['csv_row'])) {
            $row = RemainsOemBundleJsonlRow::padCsvRow(array_map(static fn ($c) => (string) $c, $payload['csv_row']));
        } else {
            $csvAssoc = $payload['csv'] ?? null;
            $row = RemainsOemBundleJsonlRow::numericRowFromCsvAssoc(is_array($csvAssoc) ? $csvAssoc : []);
        }
        $row = array_pad($row, 16, '');

        $skuRaw = trim((string) ($row[2] ?? ''));
        $name = trim((string) ($row[3] ?? ''));
        if ($skuRaw === '' || $name === '') {
            $out['error'] = 'invalid_csv_row';

            return $out;
        }

        $sku = mb_strlen($skuRaw) <= 100
            ? $skuRaw
            : (mb_substr($skuRaw, 0, 90).'_'.substr(md5($skuRaw), 0, 8));
        $out['sku'] = $sku;
        $out['name'] = $name;

        $ctxRaw = $payload['import_context'] ?? null;
        if (is_array($ctxRaw) && isset($ctxRaw['vehicles']) && is_array($ctxRaw['vehicles'])) {
            foreach ($ctxRaw['vehicles'] as $vm) {
                if (is_array($vm) && isset($vm['make'])) {
                    $m = trim((string) $vm['make']);
                    if ($m !== '') {
                        $out['import_context_vehicle_make'] = $m;
                        break;
                    }
                }
            }
        }

        $ctxMake = $out['import_context_vehicle_make'];
        if ($ctxMake !== null && $ctxMake !== '') {
            $out['name_lead_make_conflicts_context'] = StorefrontVehicleProductNameConsistency::conflictsWithSelectedVehicleMake(
                $name,
                $ctxMake
            );
        }

        $out['ok'] = true;

        return $out;
    }

    /**
     * Одна строка JSONL из {@see ExportRemainsCsvOemBundlesCommand}: товар + остаток из CSV,
     * обогащение из сохранённого `catalog` без запросов к RapidAPI.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   ok: bool,
     *   skipped: 'existing'|'invalid'|null,
     *   error: string|null,
     *   product_id: int|null,
     *   stats_patch: array<string, int>,
     * }
     */
    public function importFromOemBundleJsonlPayload(
        array $payload,
        bool $dryRun = false,
        bool $skipExisting = true,
        ?bool $downloadCatalogImages = null
    ): array {
        $emptyStats = static fn (): array => [
            'imported' => 0,
            'created_products' => 0,
            'skipped_existing' => 0,
            'created_vehicles' => 0,
            'attached_vehicles' => 0,
            'catalog_images_attached' => 0,
            'catalog_images_failed' => 0,
            'catalog_images_no_url' => 0,
            'catalog_vehicles_attached' => 0,
            'catalog_enrichment_failed' => 0,
            'catalog_primary_oem_added' => 0,
            'catalog_metadata_oem_extra' => 0,
            'catalog_metadata_cross' => 0,
            'catalog_metadata_attributes' => 0,
            'catalog_storefront_categories' => 0,
        ];

        $out = [
            'ok' => false,
            'skipped' => null,
            'error' => null,
            'product_id' => null,
            'stats_patch' => $emptyStats(),
        ];

        $catalog = $payload['catalog'] ?? null;
        if (! is_array($catalog)) {
            $out['error'] = 'missing_catalog';

            return $out;
        }

        $enriched = $this->autoPartsCatalogService->enrichmentPayloadFromFullOemBundle($catalog);
        if (($enriched['source'] ?? 'none') === 'none') {
            $out['error'] = 'bundle_not_oem';

            return $out;
        }

        if (isset($payload['csv_row']) && is_array($payload['csv_row'])) {
            $row = RemainsOemBundleJsonlRow::padCsvRow(array_map(static fn ($c) => (string) $c, $payload['csv_row']));
        } else {
            $csvAssoc = $payload['csv'] ?? null;
            $row = RemainsOemBundleJsonlRow::numericRowFromCsvAssoc(is_array($csvAssoc) ? $csvAssoc : []);
        }
        $row = array_pad($row, 16, '');

        $code = trim((string) ($row[1] ?? ''));
        $skuRaw = trim((string) ($row[2] ?? ''));
        $name = trim((string) ($row[3] ?? ''));
        if ($skuRaw === '' || $name === '') {
            $out['error'] = 'invalid_csv_row';

            return $out;
        }

        $sku = mb_strlen($skuRaw) <= 100
            ? $skuRaw
            : (mb_substr($skuRaw, 0, 90).'_'.substr(md5($skuRaw), 0, 8));

        if ($skipExisting && Product::query()->where('sku', $sku)->exists()) {
            $out['ok'] = true;
            $out['skipped'] = 'existing';
            $out['stats_patch']['skipped_existing'] = 1;

            return $out;
        }

        $ctxRaw = $payload['import_context'] ?? null;
        $context = ['standalone' => true, 'part_brand_id' => null, 'vehicles' => []];
        if (is_array($ctxRaw) && array_key_exists('standalone', $ctxRaw)) {
            $context['standalone'] = (bool) $ctxRaw['standalone'];
            $context['part_brand_id'] = isset($ctxRaw['part_brand_id']) && is_numeric($ctxRaw['part_brand_id'])
                ? (int) $ctxRaw['part_brand_id']
                : null;
            $vehicles = [];
            if (isset($ctxRaw['vehicles']) && is_array($ctxRaw['vehicles'])) {
                foreach ($ctxRaw['vehicles'] as $vm) {
                    if (is_array($vm) && isset($vm['make'])) {
                        $vehicles[] = [
                            'make' => (string) $vm['make'],
                            'model' => isset($vm['model']) ? (is_string($vm['model']) ? $vm['model'] : null) : null,
                        ];
                    }
                }
            }
            $context['vehicles'] = $vehicles;
        }

        $quantity = $this->parseStocksUnsignedIntColumn($row[8] ?? '0', 'Остаток', $sku);
        $reserved = $this->parseStocksUnsignedIntColumn($row[6] ?? '0', 'Резерв', $sku);
        $costPrice = $this->parseDecimal($row[9] ?? null);
        $salePrice = $this->parseDecimal($row[11] ?? null);
        $days = isset($row[13]) && $row[13] !== ''
            ? $this->parseDaysInWarehouseColumn($row[13], $sku)
            : null;

        if ($dryRun) {
            $out['ok'] = true;
            if (Product::query()->where('sku', $sku)->exists()) {
                $out['skipped'] = 'existing';
                $out['stats_patch']['skipped_existing'] = 1;
            } else {
                $out['stats_patch']['imported'] = 1;
            }

            return $out;
        }

        $downloadImages = $downloadCatalogImages ?? (bool) config('remains_stock_import.download_catalog_images', true);

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

        $stats = &$out['stats_patch'];
        $createdProduct = $this->insertNewRemainsProductIfNotExists(
            $sku,
            $code,
            $name,
            $quantity,
            $reserved,
            $costPrice,
            $salePrice,
            $days,
            $warehouse,
            $context,
            $stats
        );

        if ($createdProduct === null) {
            if (($stats['skipped_existing'] ?? 0) > 0) {
                $out['ok'] = true;
                $out['skipped'] = 'existing';
            } else {
                $out['error'] = 'insert_failed';
            }

            return $out;
        }

        try {
            $this->applyEnrichmentFromLookupArray(
                $createdProduct->fresh(),
                $enriched,
                $downloadImages,
                $stats
            );
            $extra = $this->catalogProductMetadataSync->syncFromFullOemBundle($createdProduct->fresh(), $catalog);
            $stats['catalog_metadata_oem_extra'] += (int) ($extra['oem_added'] ?? 0);
            $stats['catalog_metadata_attributes'] += (int) ($extra['attributes_upserted'] ?? 0);
        } catch (\Throwable $e) {
            $stats['catalog_enrichment_failed']++;
            Log::warning('remains_import_jsonl_catalog_enrichment', [
                'sku' => $sku,
                'product_id' => $createdProduct->id,
                'message' => $e->getMessage(),
            ]);
        }

        $sleepMs = max(0, (int) config('remains_stock_import.catalog_image_sleep_ms', 80));
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }

        $out['ok'] = true;
        $out['product_id'] = $createdProduct->id;

        return $out;
    }

    /**
     * @param  array{standalone: bool, part_brand_id: ?int, vehicles: list<array{make: string, model: ?string}>}  $context
     * @param  array<string, int>  $stats
     */
    private function insertNewRemainsProductIfNotExists(
        string $sku,
        string $code,
        string $name,
        int $quantity,
        int $reserved,
        ?float $costPrice,
        ?float $salePrice,
        ?int $days,
        Warehouse $warehouse,
        array $context,
        array &$stats
    ): ?Product {
        $createdProduct = null;

        DB::transaction(function () use (
            &$stats,
            &$createdProduct,
            $warehouse,
            $sku,
            $code,
            $name,
            $quantity,
            $reserved,
            $costPrice,
            $salePrice,
            $days,
            $context
        ) {
            if (Product::query()->where('sku', $sku)->exists()) {
                $stats['skipped_existing']++;

                return;
            }

            $product = new Product;
            $product->sku = $sku;
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

            if ($context['standalone']) {
                $product->brand_id = Brand::platformUnknownFallback()->id;
            } elseif ($context['part_brand_id'] !== null) {
                $product->brand_id = $context['part_brand_id'];
            } else {
                $product->brand_id = Brand::platformUnknownFallback()->id;
            }

            $brandName = Brand::query()->whereKey($product->brand_id)->value('name');
            $product->slug = ProductCatalogSlug::unique(
                (string) $product->name,
                $brandName !== null ? (string) $brandName : null
            );

            $product->save();
            $stats['created_products']++;
            $stats['imported']++;

            if ($this->catalogProductMetadataSync->ensurePrimaryOemFromSku($product)) {
                $stats['catalog_primary_oem_added']++;
            }

            foreach ($context['vehicles'] as $vm) {
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
                    $product->vehicles()->attach($vehicle->id, ['oem_number' => $oem !== '' ? $oem : null]);
                    $stats['attached_vehicles']++;
                }
            }

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

            $createdProduct = $product;
        });

        return $createdProduct;
    }

    /**
     * @param  array<string, mixed>  $enriched
     * @param  array<string, int>  $stats
     */
    private function applyEnrichmentFromLookupArray(Product $product, array $enriched, bool $downloadImages, array &$stats): void
    {
        $stats['catalog_vehicles_attached'] += $this->catalogVehicleSyncService->attachFromEnrichment(
            $product,
            $enriched
        );

        $meta = $this->catalogProductMetadataSync->syncFromEnrichment($product->fresh(), $enriched);
        $stats['catalog_metadata_oem_extra'] += $meta['oem_added'];
        $stats['catalog_metadata_cross'] += $meta['cross_added'];
        $stats['catalog_metadata_attributes'] += $meta['attributes_upserted'];

        if ($this->catalogProductMetadataSync->assignStorefrontCategoryFromEnrichment($product->fresh(), $enriched)) {
            $stats['catalog_storefront_categories']++;
        }

        if ($downloadImages && ! $this->catalogProductImageDownloader->productHasUsableImages($product->fresh())) {
            $url = trim((string) ($enriched['catalog_image_url'] ?? ''));
            if ($url !== '') {
                if ($this->catalogProductImageDownloader->downloadAndAttach($product->fresh(), $url)) {
                    $stats['catalog_images_attached']++;
                } else {
                    $stats['catalog_images_failed']++;
                }
            } else {
                $stats['catalog_images_no_url']++;
            }
        }
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

    /**
     * stocks.quantity / reserved_quantity — MySQL unsignedInteger (макс. 4294967295).
     * Большие числа бывают из‑за склейки цифр в ячейке или сдвига колонок; иначе INSERT падает с 22003.
     */
    private function parseStocksUnsignedIntColumn(string $raw, string $label, string $sku): int
    {
        $f = $this->parseDecimal($raw) ?? 0.0;
        if (! is_finite($f) || $f < 0) {
            return 0;
        }
        $max = 4_294_967_295;
        if ($f > $max) {
            Log::warning('remains_import_quantity_out_of_range', [
                'sku' => $sku,
                'column' => $label,
                'raw' => $raw,
                'parsed' => $f,
            ]);

            return 0;
        }

        return (int) round($f);
    }

    /**
     * Разумный потолок для «дней на складе», чтобы не упираться в unsigned int при битой ячейке.
     */
    private function parseDaysInWarehouseColumn(string $raw, string $sku): int
    {
        $f = $this->parseDecimal($raw) ?? 0.0;
        if (! is_finite($f) || $f < 0) {
            return 0;
        }
        $max = 36_500;
        if ($f > $max) {
            Log::warning('remains_import_days_out_of_range', [
                'sku' => $sku,
                'raw' => $raw,
                'parsed' => $f,
            ]);

            return $max;
        }

        return (int) round($f);
    }
}
