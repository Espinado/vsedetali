<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoPartsCatalogService;
use App\Services\CatalogProductImageDownloader;
use App\Services\CatalogProductMetadataSyncService;
use App\Services\CatalogVehicleSyncService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Догрузка марок/моделей из TecDoc (RapidAPI) для уже импортированных товаров — для фильтра «Модель» в каталоге.
 */
class ProductsSyncCatalogVehiclesCommand extends Command
{
    protected $signature = 'products:sync-catalog-vehicles
        {--limit=0 : Обработать не больше N товаров (0 = все)}
        {--sleep=120 : Пауза между запросами к API, мс}
        {--only-without-catalog-match : Только товары, по которым каталог ещё не дал ответ (нет атрибута «Источник каталога»)}
        {--with-images : Дополнительно догружать фото по SKU, если у товара нет рабочих изображений}';

    protected $description = 'Массово переобогащает товары из RapidAPI: авто-совместимость, метаданные, витринная категория и фото';

    public function handle(
        AutoPartsCatalogService $catalog,
        CatalogVehicleSyncService $vehicleSync,
        CatalogProductMetadataSyncService $metadataSync,
        CatalogProductImageDownloader $imageDownloader
    ): int {
        if (! $catalog->isConfigured()) {
            $this->error('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $sleepMs = max(0, (int) $this->option('sleep'));
        $onlyWithoutMatch = (bool) $this->option('only-without-catalog-match');
        $withImages = (bool) $this->option('with-images');

        $query = Product::query()->active()->orderBy('id');
        if ($onlyWithoutMatch) {
            $query->whereDoesntHave('attributes', function (Builder $q): void {
                $q->where('name', 'Источник каталога');
            });
        }
        $processed = 0;
        $attachedTotal = 0;
        $oemExtra = 0;
        $cross = 0;
        $attrs = 0;
        $primaryOem = 0;
        $storefrontCat = 0;
        $imagesAttached = 0;
        $imagesFailed = 0;
        $errors = 0;

        $this->info('Синхронизация применимости и метаданных из каталога (RapidAPI)...');
        if ($onlyWithoutMatch) {
            $this->comment('Режим: только товары без атрибута «Источник каталога» (ранее каталог не нашёл деталь или синхронизация не запускалась).');
        }

        foreach ($query->lazy(50) as $product) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $codeAlt = $product->code !== null && trim((string) $product->code) !== ''
                ? trim((string) $product->code)
                : null;

            try {
                if ($metadataSync->ensurePrimaryOemFromSku($product)) {
                    $primaryOem++;
                }
                $enriched = $catalog->lookupEnrichedForStockWithCandidates((string) $product->sku, $codeAlt, (string) $product->name);
                if (($enriched['source'] ?? 'none') === 'none') {
                    $processed++;
                    if ($sleepMs > 0) {
                        usleep($sleepMs * 1000);
                    }

                    continue;
                }
                $attachedTotal += $vehicleSync->attachFromEnrichment($product, $enriched);
                $fresh = $product->fresh();
                $meta = $metadataSync->syncFromEnrichment($fresh, $enriched);
                $oemExtra += $meta['oem_added'];
                $cross += $meta['cross_added'];
                $attrs += $meta['attributes_upserted'];
                if ($metadataSync->assignStorefrontCategoryFromEnrichment($fresh, $enriched)) {
                    $storefrontCat++;
                }
                if ($withImages && ! $imageDownloader->productHasUsableImages($fresh)) {
                    $imgResult = $imageDownloader->attachFromSkuRawIfConfigured(
                        $fresh,
                        (string) $product->sku,
                        $codeAlt,
                        true
                    );
                    match ($imgResult) {
                        'attached' => $imagesAttached++,
                        'download_failed', 'api_error' => $imagesFailed++,
                        default => null,
                    };
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("SKU {$product->sku}: {$e->getMessage()}");
            }

            $processed++;
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Обработано товаров', $processed],
                ['Новых привязок товар–авто', $attachedTotal],
                ['Добавлено основных OEM из SKU', $primaryOem],
                ['Доп. OEM из каталога', $oemExtra],
                ['Кросс-номера', $cross],
                ['Атрибуты каталога (категория/источник)', $attrs],
                ['Категория витрины (products.category_id)', $storefrontCat],
                ['Фото догружено', $imagesAttached],
                ['Ошибок догрузки фото', $imagesFailed],
                ['Ошибок API', $errors],
            ]
        );

        return self::SUCCESS;
    }
}
