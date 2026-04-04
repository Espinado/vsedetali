<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Клиент RapidAPI «Auto Parts Catalog» — поиск по артикулу/OEM, категория, применимость к авто.
 *
 * На RapidAPI часть маршрутов отдаётся через query string (см. {@see searchByArticleNumber}),
 * для OEM — {@see searchByOemArticleNumber}, {@see getCrossReferencesByOemArticleNumber}, {@see getVehiclesByOemPartNumber}.
 *
 * @see https://rapidapi.com/makingdatameaningful/api/auto-parts-catalog
 */
class AutoPartsCatalogService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.auto_parts_catalog.key');
    }

    /**
     * Поиск по номеру детали (aftermarket / общий поиск).
     * На RapidAPI: GET /articles/search-by-article-no?langId=&articleNo=
     *
     * @return array<string, mixed>|null
     */
    public function searchByArticleNumber(string $articleNo): ?array
    {
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $query = http_build_query([
            'langId' => $langId,
            'articleNo' => $articleNo,
        ]);

        return $this->getJson('/articles/search-by-article-no?'.$query);
    }

    /**
     * Поиск по OEM-номеру (например BMW 13712454387).
     * GET /articles-oem/search-by-article-oem-no?langId=&articleOemNo=
     *
     * Ответ — JSON-массив записей (articleId, manufacturerId, articleProductName, …).
     *
     * @return array<int, mixed>|null
     */
    public function searchByOemArticleNumber(string $articleOemNo): ?array
    {
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $query = http_build_query([
            'langId' => $langId,
            'articleOemNo' => $articleOemNo,
        ]);

        return $this->getJson('/articles-oem/search-by-article-oem-no?'.$query);
    }

    /**
     * Уникальные бренды-поставщики aftermarket по OEM (аналоги разных производителей).
     * Данные берутся из {@see searchByOemArticleNumber}: поля supplierId, supplierName, articleNo, articleId.
     * Повторы одного supplierId (разные картинки/записи) схлопываются.
     *
     * @return array<int, array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>
     */
    public function listSuppliersByOemArticleNumber(string $articleOemNo): array
    {
        $raw = $this->searchByOemArticleNumber($articleOemNo);
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        return $this->uniqueSuppliersFromOemRows($raw);
    }

    /**
     * @param  array<int, mixed>  $oemSearchRows
     * @return array<int, array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>
     */
    protected function uniqueSuppliersFromOemRows(array $oemSearchRows): array
    {
        $bySupplier = [];
        foreach ($oemSearchRows as $row) {
            if (! is_array($row) || ! isset($row['supplierId']) || ! is_numeric($row['supplierId'])) {
                continue;
            }
            $sid = (int) $row['supplierId'];
            if (isset($bySupplier[$sid])) {
                continue;
            }
            $bySupplier[$sid] = [
                'supplierId' => $sid,
                'supplierName' => isset($row['supplierName']) ? (string) $row['supplierName'] : '',
                'articleNo' => isset($row['articleNo']) ? (string) $row['articleNo'] : null,
                'articleId' => isset($row['articleId']) && is_numeric($row['articleId'])
                    ? (int) $row['articleId']
                    : null,
            ];
        }

        $list = array_values($bySupplier);
        usort($list, fn (array $a, array $b): int => strcasecmp($a['supplierName'], $b['supplierName']));

        return $list;
    }

    /**
     * Кроссы и аналоги aftermarket по OEM (расширенная таблица, часто больше позиций, чем в OEM-поиске).
     * GET /artlookup/search-for-analogue-of-spare-parts-by-oem-number/article-oem-no/{articleOemNo}
     *
     * Ответ: countArticles, articles[] с полями supplierName, articleNo, crossManufacturerName, crossNumber, searchLevel.
     *
     * @return array<string, mixed>|null
     */
    public function getCrossReferencesByOemArticleNumber(string $articleOemNo): ?array
    {
        $oem = rawurlencode($articleOemNo);

        return $this->getJson('/artlookup/search-for-analogue-of-spare-parts-by-oem-number/article-oem-no/'.$oem);
    }

    /**
     * Аналоги других производителей (aftermarket) из кросс-таблицы: бренд + артикул + привязка к OEM.
     *
     * @param  bool  $directOemAnalogsOnly  true — только связь IAM→OEM (без цепочек IAM→OEM→IAM между аналогами)
     * @return array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>
     */
    public function listAnalogsFromCrossReferences(string $articleOemNo, bool $directOemAnalogsOnly = true): array
    {
        $raw = $this->getCrossReferencesByOemArticleNumber($articleOemNo);
        if (! is_array($raw) || ! isset($raw['articles']) || ! is_array($raw['articles'])) {
            return [];
        }

        return $this->uniqueAnalogRowsFromCrossReferenceArticles($raw['articles'], $directOemAnalogsOnly);
    }

    /**
     * @param  array<int, mixed>  $articles
     * @return array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>
     */
    protected function uniqueAnalogRowsFromCrossReferenceArticles(array $articles, bool $directOemAnalogsOnly): array
    {
        $seen = [];
        $out = [];
        foreach ($articles as $row) {
            if (! is_array($row)) {
                continue;
            }
            $level = isset($row['searchLevel']) ? trim((string) $row['searchLevel']) : '';
            if ($directOemAnalogsOnly && str_contains($level, 'IAM->OEM->IAM')) {
                continue;
            }
            if ($directOemAnalogsOnly && ! str_contains($level, 'IAM->OEM')) {
                continue;
            }
            $supplier = isset($row['supplierName']) ? (string) $row['supplierName'] : '';
            $articleNo = isset($row['articleNo']) ? (string) $row['articleNo'] : '';
            $key = $supplier."\0".$articleNo;
            if ($supplier === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'supplierName' => $supplier,
                'articleNo' => $articleNo,
                'crossManufacturerName' => isset($row['crossManufacturerName']) ? (string) $row['crossManufacturerName'] : null,
                'crossNumber' => isset($row['crossNumber']) ? (string) $row['crossNumber'] : null,
                'searchLevel' => $level,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcasecmp($a['supplierName'].$a['articleNo'], $b['supplierName'].$b['articleNo']));

        return $out;
    }

    /**
     * Категория TecDoc по внутреннему articleId.
     * GET /articles/get-article-category/article-id/{articleId}/lang-id/{langId}
     *
     * @return array<string, mixed>|null
     */
    public function getCategoryByArticleId(int $articleId): ?array
    {
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $path = "/articles/get-article-category/article-id/{$articleId}/lang-id/{$langId}";

        return $this->getJson($path);
    }

    /**
     * Список применимости по OEM: марка/модель/модификация и интервалы выпуска.
     * GET /articles-oem/selecting-a-list-of-cars-for-oem-part-number/type-id/.../manufacturer-id/.../article-oem-no/...
     *
     * @return array<int, mixed>|null
     */
    public function getVehiclesByOemPartNumber(int $manufacturerId, string $articleOemNo): ?array
    {
        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $oem = rawurlencode($articleOemNo);
        $path = '/articles-oem/selecting-a-list-of-cars-for-oem-part-number'
            ."/type-id/{$typeId}/lang-id/{$langId}/country-filter-id/{$countryId}"
            ."/manufacturer-id/{$manufacturerId}/article-oem-no/{$oem}";

        return $this->getJson($path);
    }

    /**
     * Сценарий для OEM-кода: поиск OEM → категория по первому articleId → список авто (нужен manufacturerId из поиска).
     *
     * @param  bool  $includeCrossReferences  второй запрос к artlookup — полный список аналогов aftermarket (IAM→OEM), обычно шире, чем {@see uniqueSuppliersFromOemRows}
     * @return array{
     *     oem_search: array<int, mixed>|null,
     *     suppliers: array<int, array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>,
     *     cross_reference_total: int|null,
     *     cross_reference_analogs: array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>|null,
     *     first_article_id: int|null,
     *     manufacturer_id: int|null,
     *     category: array<string, mixed>|null,
     *     vehicles: array<int, mixed>|null,
     * }
     */
    public function lookupByOemPartNumber(string $articleOemNo, bool $includeCrossReferences = false): array
    {
        $oemSearch = $this->searchByOemArticleNumber($articleOemNo);
        $rows = is_array($oemSearch) && array_is_list($oemSearch) ? $oemSearch : [];
        $first = $rows[0] ?? null;
        $articleId = is_array($first) && isset($first['articleId']) && is_numeric($first['articleId'])
            ? (int) $first['articleId']
            : null;
        $manufacturerId = is_array($first) && isset($first['manufacturerId']) && is_numeric($first['manufacturerId'])
            ? (int) $first['manufacturerId']
            : null;

        $suppliers = $this->uniqueSuppliersFromOemRows($rows);
        $category = $articleId !== null ? $this->getCategoryByArticleId($articleId) : null;
        $vehicles = $manufacturerId !== null
            ? $this->getVehiclesByOemPartNumber($manufacturerId, $articleOemNo)
            : null;

        $crossTotal = null;
        $crossAnalogs = null;
        if ($includeCrossReferences) {
            $crossRaw = $this->getCrossReferencesByOemArticleNumber($articleOemNo);
            $crossTotal = isset($crossRaw['countArticles']) && is_numeric($crossRaw['countArticles'])
                ? (int) $crossRaw['countArticles']
                : null;
            $articles = (is_array($crossRaw) && isset($crossRaw['articles']) && is_array($crossRaw['articles']))
                ? $crossRaw['articles']
                : [];
            $crossAnalogs = $this->uniqueAnalogRowsFromCrossReferenceArticles($articles, true);
        }

        return [
            'oem_search' => $oemSearch,
            'suppliers' => $suppliers,
            'cross_reference_total' => $crossTotal,
            'cross_reference_analogs' => $crossAnalogs,
            'first_article_id' => $articleId,
            'manufacturer_id' => $manufacturerId,
            'category' => $category,
            'vehicles' => $vehicles,
        ];
    }

    /**
     * Поиск по артикулу aftermarket + категория + совместимость по номеру (если доступно у провайдера).
     *
     * @return array{
     *     search: array<string, mixed>|null,
     *     search_rows: array<int, mixed>,
     *     first_article_id: int|null,
     *     category: array<string, mixed>|null,
     *     compatible_vehicles: array<string, mixed>|null,
     * }
     */
    public function lookupByPartNumber(string $articleNo): array
    {
        $search = $this->searchByArticleNumber($articleNo);
        $rows = $this->normalizeSearchRows($search);
        $firstId = $this->firstArticleId($rows);
        $category = $firstId !== null ? $this->getCategoryByArticleId($firstId) : null;

        return [
            'search' => $search,
            'search_rows' => $rows,
            'first_article_id' => $firstId,
            'category' => $category,
            'compatible_vehicles' => null,
        ];
    }

    protected function getJson(string $pathOrQuery): ?array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env (RapidAPI → Auto Parts Catalog → ключ).');
        }

        $base = rtrim((string) config('services.auto_parts_catalog.base_url'), '/');
        $url = str_starts_with($pathOrQuery, 'http') ? $pathOrQuery : $base.$pathOrQuery;
        $timeout = (int) config('services.auto_parts_catalog.timeout', 30);

        $response = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'X-RapidAPI-Key' => (string) config('services.auto_parts_catalog.key'),
                'X-RapidAPI-Host' => (string) config('services.auto_parts_catalog.host'),
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Auto Parts Catalog HTTP '.$response->status().': '.$response->body()
            );
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>|null  $search
     * @return array<int, mixed>
     */
    protected function normalizeSearchRows(?array $search): array
    {
        if ($search === null || $search === []) {
            return [];
        }

        foreach (['data', 'articles', 'items', 'result'] as $key) {
            if (isset($search[$key]) && is_array($search[$key])) {
                $inner = $search[$key];

                return array_is_list($inner) ? $inner : [];
            }
        }

        if (array_is_list($search)) {
            return $search;
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    protected function firstArticleId(array $rows): ?int
    {
        if ($rows === []) {
            return null;
        }

        $first = $rows[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        if (isset($first['articleId']) && is_numeric($first['articleId'])) {
            return (int) $first['articleId'];
        }

        if (isset($first['article_id']) && is_numeric($first['article_id'])) {
            return (int) $first['article_id'];
        }

        return null;
    }

    /**
     * Обогащение для импорта остатков: категория, OEM-поставщики, кросс-аналоги, применимость к авто.
     *
     * @return array{
     *   source: 'oem'|'article'|'none',
     *   category_main: string,
     *   category_sub: string,
     *   category_raw: array<string, mixed>|null,
     *   oem_suppliers: list<array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>,
     *   cross_analogs: list<array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>,
     *   vehicles_normalized: list<array{
     *     make: string,
     *     model: string,
     *     body_type: string,
     *     year_from: int|null,
     *     year_to: int|null,
     *     engine: string,
     *   }>,
     *   vehicles_raw: array<int, mixed>|null,
     *   first_article_id: int|null,
     *   manufacturer_id: int|null,
     * }
     */
    public function lookupEnrichedForStock(string $articleNo): array
    {
        $articleNo = trim($articleNo);
        if ($articleNo === '') {
            return $this->emptyEnrichmentPayload();
        }

        $lookup = $this->lookupByOemPartNumber($articleNo, true);
        $oemSearch = $lookup['oem_search'] ?? null;
        $oemList = is_array($oemSearch) && array_is_list($oemSearch) ? $oemSearch : [];

        if ($oemList !== []) {
            $categoryRaw = $lookup['category'] ?? null;
            $parts = $this->splitCategoryLevels($categoryRaw);
            $vehiclesRaw = $lookup['vehicles'] ?? null;
            $normalized = $this->normalizeVehicleList($vehiclesRaw);
            $crossAnalogs = $lookup['cross_reference_analogs'] ?? [];
            if (! is_array($crossAnalogs)) {
                $crossAnalogs = [];
            }

            return [
                'source' => 'oem',
                'category_main' => $parts['main'],
                'category_sub' => $parts['sub'],
                'category_raw' => is_array($categoryRaw) ? $categoryRaw : null,
                'oem_suppliers' => $lookup['suppliers'] ?? [],
                'cross_analogs' => $crossAnalogs,
                'vehicles_normalized' => $normalized,
                'vehicles_raw' => is_array($vehiclesRaw) ? $vehiclesRaw : null,
                'first_article_id' => $lookup['first_article_id'] ?? null,
                'manufacturer_id' => $lookup['manufacturer_id'] ?? null,
            ];
        }

        $search = $this->searchByArticleNumber($articleNo);
        $rows = $this->normalizeSearchRows($search);
        $firstId = $this->firstArticleId($rows);
        $categoryRaw = $firstId !== null ? $this->getCategoryByArticleId($firstId) : null;
        $parts = $this->splitCategoryLevels($categoryRaw);
        $articleSuppliers = $this->uniqueSuppliersFromOemRows($rows);
        $crossAnalogs = $this->listAnalogsFromCrossReferences($articleNo, true);

        $hasCategory = $parts['main'] !== '' || $parts['sub'] !== '';
        $hasAnything = $firstId !== null || $hasCategory || $articleSuppliers !== [] || $crossAnalogs !== [];

        if (! $hasAnything) {
            return $this->emptyEnrichmentPayload();
        }

        return [
            'source' => 'article',
            'category_main' => $parts['main'],
            'category_sub' => $parts['sub'],
            'category_raw' => is_array($categoryRaw) ? $categoryRaw : null,
            'oem_suppliers' => $articleSuppliers,
            'cross_analogs' => $crossAnalogs,
            'vehicles_normalized' => [],
            'vehicles_raw' => null,
            'first_article_id' => $firstId,
            'manufacturer_id' => null,
        ];
    }

    /**
     * @return array{
     *   source: 'none',
     *   category_main: string,
     *   category_sub: string,
     *   category_raw: null,
     *   oem_suppliers: list<array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>,
     *   cross_analogs: list<array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>,
     *   vehicles_normalized: list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>,
     *   vehicles_raw: null,
     *   first_article_id: null,
     *   manufacturer_id: null,
     * }
     */
    protected function emptyEnrichmentPayload(): array
    {
        return [
            'source' => 'none',
            'category_main' => '',
            'category_sub' => '',
            'category_raw' => null,
            'oem_suppliers' => [],
            'cross_analogs' => [],
            'vehicles_normalized' => [],
            'vehicles_raw' => null,
            'first_article_id' => null,
            'manufacturer_id' => null,
        ];
    }

    /**
     * Разбор дерева категории TecDoc (разные варианты ключей в JSON).
     *
     * @param  array<string, mixed>|null  $category
     * @return array{main: string, sub: string}
     */
    public function splitCategoryLevels(?array $category): array
    {
        if ($category === null || $category === []) {
            return ['main' => '', 'sub' => ''];
        }

        $main = $this->pickStringFrom($category, [
            'parentCategoryName',
            'parentCategoryNameLong',
            'parentCategoryNameShort',
            'parentName',
            'parentCategoryNameTrans',
        ]);
        $sub = $this->pickStringFrom($category, [
            'categoryName',
            'categoryNameLong',
            'categoryNameShort',
            'categoryNameTrans',
            'childCategoryName',
            'subCategoryName',
        ]);

        if (isset($category['parentCategory']) && is_array($category['parentCategory'])) {
            $p = $category['parentCategory'];
            $main = $main ?: $this->pickStringFrom($p, ['name', 'categoryName', 'categoryNameLong']);
        }

        if ($main === '' && $sub !== '') {
            return ['main' => $sub, 'sub' => ''];
        }

        if ($main !== '' && $sub === '') {
            return ['main' => $main, 'sub' => ''];
        }

        return ['main' => $main, 'sub' => $sub];
    }

    /**
     * @param  array<int, mixed>|null  $vehicles
     * @return list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>
     */
    public function normalizeVehicleList(?array $vehicles): array
    {
        if ($vehicles === null || $vehicles === []) {
            return [];
        }
        if (isset($vehicles['data']) && is_array($vehicles['data'])) {
            $vehicles = $vehicles['data'];
        }
        if (! array_is_list($vehicles)) {
            return [];
        }

        $out = [];
        foreach ($vehicles as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $this->normalizeVehicleRow($row);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}
     */
    public function normalizeVehicleRow(array $row): array
    {
        $make = $this->pickStringFrom($row, [
            'vehicleManufacturerName',
            'manufacturerName',
            'manuName',
            'makeName',
            'vehicleManufacturerNameShort',
        ]);
        $model = $this->pickStringFrom($row, [
            'vehicleModelName',
            'modelName',
            'modelNameShort',
            'vehicleModelNameShort',
        ]);
        $body = $this->pickStringFrom($row, [
            'constructionTypeName',
            'bodyTypeName',
            'bodyTypeNameShort',
            'vehicleBodyTypeName',
        ]);
        $yearFrom = $this->pickIntFrom($row, [
            'yearOfConstructionFrom',
            'yearFrom',
            'constructionFrom',
        ]);
        $yearTo = $this->pickIntFrom($row, [
            'yearOfConstructionTo',
            'yearTo',
            'constructionTo',
        ]);
        $engine = $this->formatEngineFromRow($row);

        return [
            'make' => $make,
            'model' => $model,
            'body_type' => $body,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'engine' => $engine,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    protected function pickStringFrom(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $v = $data[$key];
            if ($v === null) {
                continue;
            }
            if (is_string($v) || is_numeric($v)) {
                $s = trim((string) $v);

                return $s;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    protected function pickIntFrom(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $v = $data[$key];
            if ($v === null || $v === '') {
                continue;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function formatEngineFromRow(array $row): string
    {
        $liters = $this->pickStringFrom($row, [
            'capacityLiters',
            'capacityLiter',
            'liters',
            'engineCapacityLiter',
        ]);
        $cc = $this->pickStringFrom($row, [
            'cc',
            'ccm',
            'ccmTech',
            'capacityCC',
            'capacityCcm',
        ]);
        if ($liters !== '' && is_numeric(str_replace(',', '.', $liters))) {
            $n = (float) str_replace(',', '.', $liters);

            return $n.' л';
        }
        if ($cc !== '' && is_numeric($cc)) {
            return $cc.' см³';
        }

        $raw = $this->pickStringFrom($row, [
            'engineCodes',
            'engineCode',
            'capacityTech',
        ]);

        return $raw;
    }
}
