<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонкий клиент RapidAPI «TecDoc Catalog» (ronhartman).
 *
 * @see https://rapidapi.com/ronhartman/api/tecdoc-catalog
 *
 * Используется консольной командой {@see \App\Console\Commands\CatalogTecdocOemLookupByOemCommand}
 * для сверки «чистых» TecDoc-категорий по OEM с тем, что назначено у товара на витрине.
 * Ключ, host и base_url — из {@see config('services.tecdoc_catalog')}.
 *
 * Пути эндпоинтов намеренно идентичны «auto-parts-catalog» того же автора; если RapidAPI
 * отдаёт 404 на каком-то маршруте — настройте его через env в будущем.
 */
class TecDocCatalogService
{
    public function __construct(protected AutoPartsCatalogService $categoryParser)
    {
    }

    public function isConfigured(): bool
    {
        return trim((string) config('services.tecdoc_catalog.key')) !== ''
            && trim((string) config('services.tecdoc_catalog.base_url')) !== ''
            && trim((string) config('services.tecdoc_catalog.host')) !== '';
    }

    /**
     * Поиск по OEM номеру. Возвращает список строк поиска с articleId / manufacturerId.
     *
     * @return list<array<string, mixed>>
     */
    public function searchByOemNumber(string $oem): array
    {
        $oem = trim($oem);
        if ($oem === '') {
            return [];
        }

        $langId = (int) config('services.tecdoc_catalog.lang_id');
        $query = http_build_query([
            'langId' => $langId,
            'articleOemNo' => $oem,
        ]);

        $raw = $this->getJson('/articles-oem/search-by-article-oem-no?'.$query);
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Категория TecDoc по articleId. Возвращает исходный JSON (или null при ошибке).
     *
     * @return array<string, mixed>|null
     */
    public function getCategoryByArticleId(int $articleId): ?array
    {
        if ($articleId <= 0) {
            return null;
        }

        $langId = (int) config('services.tecdoc_catalog.lang_id');

        return $this->getJson('/articles/get-article-category/article-id/'.$articleId.'/lang-id/'.$langId);
    }

    /**
     * Высокоуровневый сценарий: OEM → первый articleId → категория (main/sub).
     *
     * @return array{
     *     oem: string,
     *     found: bool,
     *     article_id: int|null,
     *     manufacturer_id: int|null,
     *     article_name: string,
     *     supplier_name: string,
     *     category_main: string,
     *     category_sub: string,
     *     category_raw: array<string, mixed>|null
     * }
     */
    public function lookupCategoryForOem(string $oem): array
    {
        $empty = [
            'oem' => trim($oem),
            'found' => false,
            'article_id' => null,
            'manufacturer_id' => null,
            'article_name' => '',
            'supplier_name' => '',
            'category_main' => '',
            'category_sub' => '',
            'category_raw' => null,
        ];

        $rows = $this->searchByOemNumber($oem);
        if ($rows === []) {
            return $empty;
        }

        $first = $rows[0];
        $articleId = isset($first['articleId']) && is_numeric($first['articleId'])
            ? (int) $first['articleId']
            : null;
        $manufacturerId = isset($first['manufacturerId']) && is_numeric($first['manufacturerId'])
            ? (int) $first['manufacturerId']
            : null;
        $articleName = isset($first['articleProductName']) ? (string) $first['articleProductName'] : '';
        $supplier = isset($first['supplierName']) ? (string) $first['supplierName'] : '';

        $category = $articleId !== null ? $this->getCategoryByArticleId($articleId) : null;
        $split = $this->categoryParser->splitCategoryLevels($category);

        return [
            'oem' => trim($oem),
            'found' => $articleId !== null,
            'article_id' => $articleId,
            'manufacturer_id' => $manufacturerId,
            'article_name' => $articleName,
            'supplier_name' => $supplier,
            'category_main' => $split['main'],
            'category_sub' => $split['sub'],
            'category_raw' => is_array($category) ? $category : null,
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    protected function getJson(string $pathOrQuery): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $base = rtrim((string) config('services.tecdoc_catalog.base_url'), '/');
        $url = $base.'/'.ltrim($pathOrQuery, '/');
        $timeout = (int) config('services.tecdoc_catalog.timeout', 30);

        $ca = storage_path('certs/cacert.pem');
        $request = Http::withHeaders([
            'X-RapidAPI-Key' => (string) config('services.tecdoc_catalog.key'),
            'X-RapidAPI-Host' => (string) config('services.tecdoc_catalog.host'),
            'Accept' => 'application/json',
        ])->timeout($timeout);

        if (is_file($ca)) {
            $request = $request->withOptions(['verify' => $ca]);
        }

        try {
            /** @var Response $resp */
            $resp = $request->get($url);
        } catch (\Throwable $e) {
            Log::warning('TecDocCatalogService: HTTP error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $resp->ok()) {
            Log::info('TecDocCatalogService: non-200', [
                'url' => $url,
                'status' => $resp->status(),
                'body' => mb_substr((string) $resp->body(), 0, 500),
            ]);

            return null;
        }

        $data = $resp->json();

        return is_array($data) ? $data : null;
    }
}
