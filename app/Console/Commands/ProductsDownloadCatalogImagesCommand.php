<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogProductImageDownloader;
use Illuminate\Console\Command;

/**
 * Догрузка фото из RapidAPI для товаров без изображений (плоский каталог storage/app/public/products/).
 */
class ProductsDownloadCatalogImagesCommand extends Command
{
    protected $signature = 'products:download-catalog-images
        {--limit=0 : Обработать не больше N товаров (0 = все)}
        {--sleep=80 : Пауза между попытками, мс}';

    protected $description = 'Скачивает фото каталога по SKU; подчищает «битые» ссылки на файлы (БД без storage) и догружает';

    public function handle(CatalogProductImageDownloader $downloader): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $sleepMs = max(0, (int) $this->option('sleep'));

        $query = Product::query()
            ->active()
            ->orderBy('id');

        $processed = 0;
        $attached = 0;
        $failed = 0;
        $skipped = [
            'no_api' => 0,
            'no_url' => 0,
        ];

        $this->info('Поиск фото по API для товаров без картинок...');
        $this->line('Файлы сохраняются в storage/app/public/products/ (URL: /storage/products/…, без подпапок).');

        foreach ($query->lazy(50) as $product) {
            if ($downloader->productHasUsableImages($product)) {
                continue;
            }

            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $codeAlt = $product->code !== null && trim((string) $product->code) !== ''
                ? trim((string) $product->code)
                : null;

            try {
                $result = $downloader->attachFromSkuRawIfConfigured($product, (string) $product->sku, $codeAlt, true);
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("SKU {$product->sku}: {$e->getMessage()}");
                $processed++;
                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }

                continue;
            }

            match ($result) {
                'attached' => $attached++,
                'download_failed', 'api_error' => $failed++,
                'no_url' => $skipped['no_url']++,
                'no_api' => $skipped['no_api']++,
                default => null,
            };

            $processed++;
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Обработано товаров', $processed],
                ['Фото сохранено', $attached],
                ['Ошибок API/скачивания', $failed],
                ['Нет URL в ответе API', $skipped['no_url']],
                ['Ключ RapidAPI не задан', $skipped['no_api']],
            ]
        );

        return self::SUCCESS;
    }
}
