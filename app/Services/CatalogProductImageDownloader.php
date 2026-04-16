<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Скачивание изображения детали по URL из каталога (например s3image из RapidAPI) в public disk.
 */
class CatalogProductImageDownloader
{
    /**
     * Имена файлов каталога в public disk: `products/{prefix}{random}.ext` (без подпапок).
     * Сброс импорта удаляет только такие файлы + устаревший каталог `products/catalog/`.
     */
    public const CATALOG_FLAT_FILENAME_PREFIX = 'catalog-img-';

    public function __construct(
        protected AutoPartsCatalogService $catalog
    ) {}

    /**
     * Удаляет строки product_images, указывающие на отсутствующий локальный файл
     * (после восстановления БД без файлов, очистки storage и т.п.). URL с http(s) не трогаем.
     */
    public function pruneMissingLocalFilesForProduct(Product $product): int
    {
        $disk = Storage::disk('public');
        $deleted = 0;

        foreach (ProductImage::query()->where('product_id', $product->id)->cursor() as $image) {
            $path = trim((string) $image->path);
            if ($path === '') {
                $image->delete();
                $deleted++;

                continue;
            }
            if (preg_match('#^https?://#i', $path) === 1) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', $path), '/');
            $rel = preg_replace('#^storage/#', '', $rel) ?? $rel;
            if (! $disk->exists($rel)) {
                $image->delete();
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * После {@see pruneMissingLocalFilesForProduct}: есть ли хотя бы одно изображение с рабочим файлом или внешним URL.
     */
    public function productHasUsableImages(Product $product): bool
    {
        $this->pruneMissingLocalFilesForProduct($product);
        $product->unsetRelation('images');

        return $product->images()->exists();
    }

    /**
     * Запрос URL картинки у API и сохранение файла для товара (если ещё нет изображений).
     *
     * @return 'attached'|'no_api'|'has_images'|'no_url'|'api_error'|'download_failed'
     */
    public function attachFromArticleNumberIfConfigured(Product $product, string $articleNumber): string
    {
        if ($this->productHasUsableImages($product)) {
            return 'has_images';
        }

        if (! $this->catalog->isConfigured()) {
            return 'no_api';
        }

        try {
            $url = $this->catalog->resolveCatalogImageUrl($articleNumber);
        } catch (\Throwable $e) {
            Log::warning('catalog_image_resolve_api', [
                'product_id' => $product->id,
                'article' => $articleNumber,
                'message' => $e->getMessage(),
            ]);

            return 'api_error';
        }

        if ($url === null || $url === '') {
            return 'no_url';
        }

        return $this->downloadAndAttach($product, $url) ? 'attached' : 'download_failed';
    }

    /**
     * Как {@see attachFromArticleNumberIfConfigured}, но перебирает варианты номера (SKU, до «/», колонка «Код» и т.д.).
     *
     * @return 'attached'|'no_api'|'has_images'|'no_url'|'api_error'|'download_failed'
     */
    public function attachFromSkuRawIfConfigured(
        Product $product,
        string $skuRaw,
        ?string $alternateCode = null,
        bool $skipUsableImagesCheck = false,
    ): string {
        if (! $skipUsableImagesCheck && $this->productHasUsableImages($product)) {
            return 'has_images';
        }

        if (! $this->catalog->isConfigured()) {
            return 'no_api';
        }

        try {
            $url = $this->catalog->resolveCatalogImageUrlWithCandidates($skuRaw, $alternateCode);
        } catch (\Throwable $e) {
            Log::warning('catalog_image_resolve_api', [
                'product_id' => $product->id,
                'sku' => $skuRaw,
                'message' => $e->getMessage(),
            ]);

            return 'api_error';
        }

        if ($url === null || $url === '') {
            return 'no_url';
        }

        return $this->downloadAndAttach($product, $url) ? 'attached' : 'download_failed';
    }

    /**
     * HTTP GET по прямому URL, проверка MIME и запись в storage/app/public.
     */
    public function downloadAndAttach(Product $product, string $url): bool
    {
        $url = trim($url);
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return false;
        }

        $maxBytes = max(256 * 1024, (int) config('remains_stock_import.catalog_image_max_bytes', 6 * 1024 * 1024));
        $timeout = (int) config('services.auto_parts_catalog.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => 'vsedetalki.ru-catalog-import/1.0',
                    'Accept' => 'image/webp,image/jpeg,image/png,image/gif,*/*',
                ])
                ->withOptions(['allow_redirects' => true])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('catalog_image_download_http', [
                'product_id' => $product->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('catalog_image_download_status', [
                'product_id' => $product->id,
                'url' => $url,
                'status' => $response->status(),
            ]);

            return false;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > $maxBytes) {
            return false;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        $ext = $this->extensionForMime($mime);
        if ($ext === null) {
            $ext = $this->extensionFromUrl($url);
        }
        if ($ext === null) {
            Log::warning('catalog_image_unknown_mime', [
                'product_id' => $product->id,
                'mime' => $mime,
                'url' => $url,
            ]);

            return false;
        }
        $name = self::CATALOG_FLAT_FILENAME_PREFIX.Str::lower(Str::random(16)).'.'.$ext;
        $relativePath = 'products/'.$name;

        Storage::disk('public')->put($relativePath, $body);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => $relativePath,
            'alt' => Str::limit($product->name, 255, ''),
            'sort' => 0,
            'is_main' => true,
        ]);

        return true;
    }

    protected function extensionForMime(string $mime): ?string
    {
        return match ($mime) {
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => null,
        };
    }

    protected function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif'], true)
            ? ($ext === 'jpeg' ? 'jpg' : $ext)
            : null;
    }
}
