<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Клиент RapidAPI «Auto Parts Catalog» — поиск по артикулу/OEM, категория, применимость к авто.
 *
 * На RapidAPI часть маршрутов отдаётся через query string (см. {@see searchByArticleNumber}),
 * для OEM — {@see searchByOemArticleNumber}, {@see getCrossReferencesByOemArticleNumber}, {@see getSelectArticleCrossReferencesByArticleId}, {@see getVehiclesByOemPartNumber}.
 *
 * @see https://rapidapi.com/makingdatameaningful/api/auto-parts-catalog
 * Список путей эндпоинтов (в т.ч. get-compatible-cars-by-article-number, /articles/details/…) см. также Apify Actor «tecdoc» от того же поставщика данных.
 */
class AutoPartsCatalogService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.auto_parts_catalog.key');
    }

    /**
     * Локальный CA bundle (Mozilla) — см. {@see storage_path('certs/cacert.pem')}; снимает cURL error 77 на Windows, если php.ini указывает на несуществующий cacert.pem.
     *
     * @return array{verify: string}|array{}
     */
    protected function httpTlsOptionsForCatalog(): array
    {
        $ca = storage_path('certs/cacert.pem');

        return is_file($ca) ? ['verify' => $ca] : [];
    }

    /**
     * Варианты номера для запросов к каталогу: сегмент до «/», полный SKU, без разделителей, только буквы/цифры, колонка «Код» из CSV.
     *
     * @return list<string>
     */
    public function partNumberSearchCandidates(string $skuRaw, ?string $alternateCode = null): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['candidate'],
            $this->partNumberSearchCandidatesDetailed($skuRaw, $alternateCode)
        );
    }

    /**
     * @return list<array{candidate: string, origin: string, allowed: bool}>
     */
    public function partNumberSearchCandidatesDetailed(string $skuRaw, ?string $alternateCode = null): array
    {
        $skuRaw = trim($skuRaw);
        $alternateCode = $alternateCode !== null ? trim($alternateCode) : '';

        $out = [];
        $seen = [];
        $push = function (string $s, string $origin) use (&$out, &$seen): void {
            $s = trim($s);
            if ($s === '') {
                return;
            }
            $s = Str::limit($s, 100, '');
            if ($s === '' || isset($seen[$s])) {
                return;
            }
            $seen[$s] = true;
            $out[] = [
                'candidate' => $s,
                'origin' => $origin,
                'allowed' => ! str_starts_with($origin, 'alternate')
                    || ! $this->isRiskyAlternateCandidate($s),
            ];
        };

        $primary = trim(Str::limit(explode('/', $skuRaw, 2)[0], 100, ''));
        if ($primary === '') {
            $primary = Str::limit($skuRaw, 100, '');
        }
        $push($primary, 'sku_primary');

        $full = Str::limit($skuRaw, 100, '');
        $push($full, 'sku_full');

        $push($this->compactPartNumber($primary), 'sku_primary_compact');
        $push($this->compactPartNumber($full), 'sku_full_compact');
        $push($this->alnumOnlyPartNumber($primary), 'sku_primary_alnum');
        $push($this->alnumOnlyPartNumber($full), 'sku_full_alnum');

        if ($alternateCode !== '') {
            $push($alternateCode, 'alternate_raw');
            $push($this->compactPartNumber($alternateCode), 'alternate_compact');
            $push($this->alnumOnlyPartNumber($alternateCode), 'alternate_alnum');
        }

        $max = max(1, (int) config('remains_stock_import.catalog_search_candidate_limit', 8));

        return array_slice($out, 0, $max);
    }

    /**
     * Как {@see lookupEnrichedForStock}, но перебирает кандидатов до первого непустого ответа (меньше «не найдено» из-за формата номера).
     *
     * @return array<string, mixed>
     */
    public function lookupEnrichedForStockWithCandidates(string $skuRaw, ?string $alternateCode = null, ?string $expectedProductName = null): array
    {
        $best = $this->emptyEnrichmentPayload();
        $bestScore = PHP_INT_MIN;
        $minScore = (int) config('remains_stock_import.catalog_candidate_min_score', 20);

        foreach ($this->partNumberSearchCandidatesDetailed($skuRaw, $alternateCode) as $row) {
            if (! ($row['allowed'] ?? true)) {
                continue;
            }

            $candidate = (string) ($row['candidate'] ?? '');
            if ($candidate === '') {
                continue;
            }

            $enriched = $this->lookupEnrichedForStock($candidate);
            if (($enriched['source'] ?? 'none') === 'none') {
                continue;
            }

            $score = $this->scoreEnrichmentCandidateMatch(
                $candidate,
                (string) ($row['origin'] ?? ''),
                $expectedProductName,
                $enriched
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $enriched;
                $best['candidate_used'] = $candidate;
                $best['candidate_origin'] = (string) ($row['origin'] ?? '');
                $best['candidate_score'] = $score;
            }

            if ($score >= $minScore) {
                return $best;
            }
        }

        if ($bestScore >= $minScore) {
            return $best;
        }

        return $this->emptyEnrichmentPayload();
    }

    protected function isRiskyAlternateCandidate(string $candidate): bool
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return true;
        }

        return ctype_digit($candidate) && strlen($candidate) <= 4;
    }

    /**
     * Простая оценка релевантности ответа каталога для кандидата номера.
     * Низкий балл означает «шумный hit» (часто короткий код из колонки "Код").
     *
     * @param  array<string, mixed>  $enriched
     */
    protected function scoreEnrichmentCandidateMatch(
        string $candidate,
        string $origin,
        ?string $expectedProductName,
        array $enriched
    ): int {
        $score = match ($origin) {
            'sku_primary' => 120,
            'sku_full' => 110,
            'sku_primary_compact', 'sku_full_compact' => 95,
            'sku_primary_alnum', 'sku_full_alnum' => 85,
            'alternate_raw', 'alternate_compact', 'alternate_alnum' => 25,
            default => 0,
        };

        if (str_starts_with($origin, 'alternate') && $this->isRiskyAlternateCandidate($candidate)) {
            $score -= 80;
        }

        $expected = trim((string) $expectedProductName);
        if ($expected !== '') {
            $catalogLabel = trim(implode(' ', array_filter([
                (string) ($enriched['first_article_name'] ?? ''),
                (string) ($enriched['category_main'] ?? ''),
                (string) ($enriched['category_sub'] ?? ''),
            ], static fn (?string $v): bool => trim((string) $v) !== '')));

            $overlap = $this->keywordOverlapRatio($expected, $catalogLabel);
            $score += (int) round($overlap * 40);
            if ($overlap <= 0.0) {
                $score -= 20;
            }
        }

        return $score;
    }

    protected function keywordOverlapRatio(string $left, string $right): float
    {
        $leftTokens = $this->tokenizeForRelevance($left);
        $rightTokens = $this->tokenizeForRelevance($right);
        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $hits = 0;
        foreach ($leftTokens as $token) {
            if (isset($rightTokens[$token])) {
                $hits++;
            }
        }

        return $hits / max(1, count($leftTokens));
    }

    /**
     * @return array<string, true>
     */
    protected function tokenizeForRelevance(string $value): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($value)) ?: [];
        $tokens = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || mb_strlen($p) < 4) {
                continue;
            }
            $tokens[$p] = true;
        }

        return $tokens;
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
     * Кросс-номера по внутреннему articleId TecDoc (другой маршрут, чем поиск по OEM-строке).
     * GET /artlookup/select-article-cross-references/article-id/{articleId}/lang-id/{langId}
     *
     * @return array<string, mixed>|null
     */
    public function getSelectArticleCrossReferencesByArticleId(int $articleId, ?int $langId = null): ?array
    {
        $langId ??= (int) config('services.auto_parts_catalog.lang_id');
        $path = '/artlookup/select-article-cross-references/article-id/'.$articleId.'/lang-id/'.$langId;

        return $this->getJson($path);
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
     * Кроссы из {@see getSelectArticleCrossReferencesByArticleId}: без фильтра только IAM→OEM (полнее список аналогов).
     *
     * @return array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>
     */
    public function listAnalogsFromArticleIdCrossReferences(int $articleId): array
    {
        $raw = $this->getSelectArticleCrossReferencesByArticleId($articleId);

        return $this->parseArticleIdCrossReferencePayload($raw);
    }

    /**
     * @return array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>
     */
    protected function parseArticleIdCrossReferencePayload(?array $raw): array
    {
        $articles = $this->extractArticlesArrayFromArtlookupResponse($raw);

        return $this->uniqueAnalogRowsFromCrossReferenceArticles($articles, false);
    }

    /**
     * Несколько независимых GET к RapidAPI за один round-trip (уменьшает суммарное время ожидания).
     *
     * @param  array<string, string>  $pathsByAlias  alias => path от base_url (например "/articles/...")
     * @return array<string, array<string, mixed>|null>
     */
    protected function rapidApiPoolGet(array $pathsByAlias): array
    {
        if ($pathsByAlias === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            throw new \RuntimeException('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env (RapidAPI → Auto Parts Catalog → ключ).');
        }

        $base = rtrim((string) config('services.auto_parts_catalog.base_url'), '/');
        $timeout = (int) config('services.auto_parts_catalog.timeout', 30);
        $headers = [
            'Accept' => 'application/json',
            'X-RapidAPI-Key' => (string) config('services.auto_parts_catalog.key'),
            'X-RapidAPI-Host' => (string) config('services.auto_parts_catalog.host'),
        ];

        $tls = $this->httpTlsOptionsForCatalog();

        $responses = Http::pool(function (Pool $pool) use ($base, $timeout, $headers, $pathsByAlias, $tls) {
            foreach ($pathsByAlias as $alias => $path) {
                $pending = $pool->as((string) $alias)
                    ->withHeaders($headers)
                    ->timeout($timeout)
                    ->acceptJson();
                if ($tls !== []) {
                    $pending = $pending->withOptions($tls);
                }
                $pending->get($base.$path);
            }
        });

        $out = [];
        foreach ($pathsByAlias as $alias => $_) {
            $response = $responses[(string) $alias] ?? null;
            if ($response instanceof \Throwable && ! $response instanceof Response) {
                Log::warning('auto_parts_catalog_pool_http', [
                    'alias' => $alias,
                    'exception' => $response::class,
                    'message' => $response->getMessage(),
                ]);
                $out[(string) $alias] = null;

                continue;
            }
            if ($response === null || ! $response->successful()) {
                if ($response instanceof Response) {
                    Log::warning('auto_parts_catalog_pool_http', [
                        'alias' => $alias,
                        'status' => $response->status(),
                    ]);
                }
                $out[(string) $alias] = null;

                continue;
            }
            $json = $response->json();
            $out[(string) $alias] = is_array($json) ? $json : null;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array<int, mixed>
     */
    protected function extractArticlesArrayFromArtlookupResponse(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        foreach (['articles', 'articleCrossReferences', 'crossReferences', 'data'] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key])) {
                $inner = $raw[$key];

                return array_is_list($inner) ? $inner : [];
            }
        }

        return array_is_list($raw) ? $raw : [];
    }

    /**
     * @param  array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>  $a
     * @param  array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>  $b
     * @return array<int, array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>
     */
    public function mergeUniqueCrossAnalogRows(array $a, array $b): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($a, $b) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $supplier = isset($row['supplierName']) ? (string) $row['supplierName'] : '';
            $articleNo = isset($row['articleNo']) ? (string) $row['articleNo'] : '';
            $key = $supplier."\0".$articleNo;
            if ($supplier === '' || $articleNo === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'supplierName' => $supplier,
                'articleNo' => $articleNo,
                'crossManufacturerName' => isset($row['crossManufacturerName']) ? (string) $row['crossManufacturerName'] : null,
                'crossNumber' => isset($row['crossNumber']) ? (string) $row['crossNumber'] : null,
                'searchLevel' => isset($row['searchLevel']) ? (string) $row['searchLevel'] : '',
            ];
        }

        usort($out, fn (array $x, array $y): int => strcasecmp($x['supplierName'].$x['articleNo'], $y['supplierName'].$y['articleNo']));

        return $out;
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
     * Список применимости по OEM (Get list of vehicles by OEM part number).
     * GET /articles-oem/selecting-a-list-of-cars-for-oem-part-number/type-id/{typeId}/lang-id/{langId}/country-filter-id/{countryId}/manufacturer-id/{manufacturerId}/article-oem-no/{oem}
     *
     * Пример (RapidAPI): …/type-id/1/lang-id/4/country-filter-id/63/manufacturer-id/93/article-oem-no/7700115294
     * Параметры type-id, lang-id, country-filter-id — из config('services.auto_parts_catalog.*'); manufacturerId — из первой строки {@see searchByOemArticleNumber}.
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
     * После первого запроса (search-by-article-oem-no) независимые GET выполняются параллельно через {@see rapidApiPoolGet} (категория, авто, кроссы по OEM и по article-id).
     *
     * @param  bool  $includeCrossReferences  запрос artlookup по OEM-строке; кроссы по article-id подмешиваются при наличии articleId
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

        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $oemEnc = rawurlencode($articleOemNo);

        $paths = [];
        if ($articleId !== null) {
            $paths['category'] = "/articles/get-article-category/article-id/{$articleId}/lang-id/{$langId}";
        }
        if ($manufacturerId !== null) {
            $paths['vehicles'] = '/articles-oem/selecting-a-list-of-cars-for-oem-part-number'
                ."/type-id/{$typeId}/lang-id/{$langId}/country-filter-id/{$countryId}"
                ."/manufacturer-id/{$manufacturerId}/article-oem-no/{$oemEnc}";
        }
        if ($includeCrossReferences) {
            $paths['cross_oem'] = '/artlookup/search-for-analogue-of-spare-parts-by-oem-number/article-oem-no/'.$oemEnc;
        }
        if ($articleId !== null) {
            $paths['cross_aid'] = '/artlookup/select-article-cross-references/article-id/'.$articleId.'/lang-id/'.$langId;
        }

        $pooled = $this->rapidApiPoolGet($paths);

        $category = isset($pooled['category']) && is_array($pooled['category']) ? $pooled['category'] : null;
        $vehicles = isset($pooled['vehicles']) && is_array($pooled['vehicles']) ? $pooled['vehicles'] : null;

        $crossTotal = null;
        $crossAnalogs = null;
        $crossRaw = $pooled['cross_oem'] ?? null;
        if ($includeCrossReferences && is_array($crossRaw)) {
            $crossTotal = isset($crossRaw['countArticles']) && is_numeric($crossRaw['countArticles'])
                ? (int) $crossRaw['countArticles']
                : null;
            $articles = (isset($crossRaw['articles']) && is_array($crossRaw['articles']))
                ? $crossRaw['articles']
                : [];
            $crossAnalogs = $this->uniqueAnalogRowsFromCrossReferenceArticles($articles, true);
        } else {
            $crossAnalogs = [];
        }

        if ($articleId !== null) {
            $extraFromArticleId = $this->parseArticleIdCrossReferencePayload($pooled['cross_aid'] ?? null);
            $crossAnalogs = $this->mergeUniqueCrossAnalogRows(
                is_array($crossAnalogs) ? $crossAnalogs : [],
                $extraFromArticleId
            );
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
     * Модели и годы выпуска в формате, близком к ответу RapidAPI:
     * - countModels
     * - models[]: modelId, modelName, modelYearFrom, modelYearTo
     *
     * Важно: собирает применимость по всем уникальным manufacturerId из OEM-поиска,
     * затем дедуплицирует модели по modelId+modelName.
     *
     * @return array{
     *     countModels: int,
     *     models: list<array{
     *         modelId: int|null,
     *         modelName: string,
     *         modelYearFrom: string|null,
     *         modelYearTo: string|null
     *     }>
     * }
     */
    public function getModelYearsByOem(string $articleOemNo): array
    {
        $oemSearch = $this->searchByOemArticleNumber($articleOemNo);
        $rows = is_array($oemSearch) && array_is_list($oemSearch) ? $oemSearch : [];

        $manufacturerIds = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = $row['manufacturerId'] ?? null;
            if (is_numeric($id)) {
                $manufacturerIds[(int) $id] = true;
            }
        }
        $manufacturerIds = array_keys($manufacturerIds);

        if ($manufacturerIds === []) {
            return [
                'countModels' => 0,
                'models' => [],
            ];
        }

        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $oemEnc = rawurlencode($articleOemNo);

        $paths = [];
        foreach ($manufacturerIds as $manufacturerId) {
            $paths['vehicles_'.$manufacturerId] = '/articles-oem/selecting-a-list-of-cars-for-oem-part-number'
                ."/type-id/{$typeId}/lang-id/{$langId}/country-filter-id/{$countryId}"
                ."/manufacturer-id/{$manufacturerId}/article-oem-no/{$oemEnc}";
        }

        $responses = $this->rapidApiPoolGet($paths);

        $allVehicles = [];
        foreach ($responses as $payload) {
            if (! is_array($payload)) {
                continue;
            }
            $vehicleRows = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
            if (! is_array($vehicleRows) || ! array_is_list($vehicleRows)) {
                continue;
            }
            foreach ($vehicleRows as $vehicleRow) {
                if (is_array($vehicleRow)) {
                    $allVehicles[] = $vehicleRow;
                }
            }
        }

        $models = $this->deduplicateModelsWithYears($allVehicles);

        return [
            'countModels' => count($models),
            'models' => $models,
        ];
    }

    /**
     * Основной OEM-сценарий:
     * категория детали, данные детали (включая первую найденную карточку), фото,
     * применимость к авто (марка/модель/годы), и аналоги.
     *
     * @return array{
     *   oem: string,
     *   category_main: string,
     *   category_sub: string,
     *   category_raw: array<string, mixed>|null,
     *   part_data: array<string, mixed>|null,
     *   part_image_url: string,
     *   vehicles: list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>,
     *   model_years: array{
     *      countModels: int,
     *      models: list<array{modelId: int|null, modelName: string, modelYearFrom: string|null, modelYearTo: string|null}>
     *   },
     *   analogs: list<array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>,
     *   oem_search_rows: array<int, mixed>|null
     * }
     */
    public function lookupPrimaryOemData(string $articleOemNo): array
    {
        $lookup = $this->lookupByOemPartNumber($articleOemNo, true);
        $oemRows = is_array($lookup['oem_search'] ?? null) ? $lookup['oem_search'] : null;
        $firstPartRow = (is_array($oemRows) && array_is_list($oemRows)) ? ($oemRows[0] ?? null) : null;

        $categoryRaw = isset($lookup['category']) && is_array($lookup['category']) ? $lookup['category'] : null;
        $category = $this->splitCategoryLevels($categoryRaw);

        $vehiclesNormalized = $this->normalizeVehicleList(
            isset($lookup['vehicles']) && is_array($lookup['vehicles']) ? $lookup['vehicles'] : null
        );

        $analogs = isset($lookup['cross_reference_analogs']) && is_array($lookup['cross_reference_analogs'])
            ? $lookup['cross_reference_analogs']
            : [];

        $partImageUrl = is_array($firstPartRow) ? ($this->extractS3ImageUrlFromArticleRow($firstPartRow) ?? '') : '';
        if ($partImageUrl === '') {
            $partImageUrl = $this->resolveCatalogImageUrl($articleOemNo) ?? '';
        }

        return [
            'oem' => $articleOemNo,
            'category_main' => $category['main'],
            'category_sub' => $category['sub'],
            'category_raw' => $categoryRaw,
            'part_data' => is_array($firstPartRow) ? $firstPartRow : null,
            'part_image_url' => $partImageUrl,
            'vehicles' => $vehiclesNormalized,
            'model_years' => $this->getModelYearsByOem($articleOemNo),
            'analogs' => $analogs,
            'oem_search_rows' => $oemRows,
        ];
    }

    /**
     * Нормализованный payload для сохранения в БД без дополнительного парсинга.
     *
     * @return array{
     *   oem: string,
     *   locale: string,
     *   category: array{main: string, sub: string, full: string},
     *   part: array{
     *      article_id: int|null,
     *      article_no: string,
     *      name: string,
     *      supplier_id: int|null,
     *      supplier_name: string,
     *      image_url: string,
     *      specifications: list<array{name: string, value: string}>,
     *      oem_numbers: list<array{brand: string, number: string}>
     *   },
     *   compatibility: array{
     *      count_models: int,
     *      models: list<array{model_id: int|null, model_name: string, year_from: string|null, year_to: string|null}>,
     *      vehicles: list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>
     *   },
     *   analogs: list<array{
     *      supplier_name: string,
     *      article_no: string,
     *      cross_manufacturer_name: string|null,
     *      cross_number: string|null,
     *      search_level: string
     *   }>,
     *   source_payload: array<string, mixed>
     * }
     */
    public function lookupPersistableOemData(string $articleOemNo): array
    {
        $articleOemNo = trim($articleOemNo);
        $primary = $this->lookupPrimaryOemData($articleOemNo);

        $partData = isset($primary['part_data']) && is_array($primary['part_data']) ? $primary['part_data'] : [];
        $articleInfo = isset($partData['articleInfo']) && is_array($partData['articleInfo']) ? $partData['articleInfo'] : [];

        $articleId = $this->pickFirstInt([
            $partData['articleId'] ?? null,
            $articleInfo['articleId'] ?? null,
        ]);
        $articleNo = $this->pickFirstString([
            $partData['articleNo'] ?? null,
            $articleInfo['articleNo'] ?? null,
        ]);
        $partName = $this->pickFirstString([
            $partData['articleProductName'] ?? null,
            $articleInfo['articleProductName'] ?? null,
        ]);
        $supplierId = $this->pickFirstInt([
            $partData['supplierId'] ?? null,
            $articleInfo['supplierId'] ?? null,
        ]);
        $supplierName = $this->pickFirstString([
            $partData['supplierName'] ?? null,
            $articleInfo['supplierName'] ?? null,
        ]);

        $specifications = $this->normalizePartSpecifications(
            isset($articleInfo['allSpecifications']) && is_array($articleInfo['allSpecifications'])
                ? $articleInfo['allSpecifications']
                : []
        );

        $oemNumbers = $this->normalizePartOemNumbers(
            isset($articleInfo['oemNo']) && is_array($articleInfo['oemNo'])
                ? $articleInfo['oemNo']
                : []
        );

        $categoryMain = (string) ($primary['category_main'] ?? '');
        $categorySub = (string) ($primary['category_sub'] ?? '');
        $categoryFull = trim($categoryMain.($categorySub !== '' ? ' > '.$categorySub : ''));

        $modelYears = isset($primary['model_years']) && is_array($primary['model_years']) ? $primary['model_years'] : ['countModels' => 0, 'models' => []];
        $modelRows = isset($modelYears['models']) && is_array($modelYears['models']) ? $modelYears['models'] : [];
        $normalizedModelRows = [];
        foreach ($modelRows as $modelRow) {
            if (! is_array($modelRow)) {
                continue;
            }
            $normalizedModelRows[] = [
                'model_id' => isset($modelRow['modelId']) && is_numeric($modelRow['modelId']) ? (int) $modelRow['modelId'] : null,
                'model_name' => trim((string) ($modelRow['modelName'] ?? '')),
                'year_from' => isset($modelRow['modelYearFrom']) && is_string($modelRow['modelYearFrom']) ? $modelRow['modelYearFrom'] : null,
                'year_to' => isset($modelRow['modelYearTo']) && is_string($modelRow['modelYearTo']) ? $modelRow['modelYearTo'] : null,
            ];
        }

        $analogsRaw = isset($primary['analogs']) && is_array($primary['analogs']) ? $primary['analogs'] : [];
        $analogs = [];
        foreach ($analogsRaw as $analog) {
            if (! is_array($analog)) {
                continue;
            }
            $supplier = trim((string) ($analog['supplierName'] ?? ''));
            $analogArticle = trim((string) ($analog['articleNo'] ?? ''));
            if ($supplier === '' || $analogArticle === '') {
                continue;
            }
            $analogs[] = [
                'supplier_name' => $supplier,
                'article_no' => $analogArticle,
                'cross_manufacturer_name' => isset($analog['crossManufacturerName']) ? (string) $analog['crossManufacturerName'] : null,
                'cross_number' => isset($analog['crossNumber']) ? (string) $analog['crossNumber'] : null,
                'search_level' => isset($analog['searchLevel']) ? (string) $analog['searchLevel'] : '',
            ];
        }

        return [
            'oem' => $articleOemNo,
            'locale' => 'ru',
            'category' => [
                'main' => $categoryMain,
                'sub' => $categorySub,
                'full' => $categoryFull,
            ],
            'part' => [
                'article_id' => $articleId,
                'article_no' => $articleNo,
                'name' => $partName,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'image_url' => (string) ($primary['part_image_url'] ?? ''),
                'specifications' => $specifications,
                'oem_numbers' => $oemNumbers,
            ],
            'compatibility' => [
                'count_models' => isset($modelYears['countModels']) && is_numeric($modelYears['countModels']) ? (int) $modelYears['countModels'] : count($normalizedModelRows),
                'models' => $normalizedModelRows,
                'vehicles' => isset($primary['vehicles']) && is_array($primary['vehicles']) ? $primary['vehicles'] : [],
            ],
            'analogs' => $analogs,
            'source_payload' => [
                'category_raw' => $primary['category_raw'] ?? null,
                'part_data' => $primary['part_data'] ?? null,
                'oem_search_rows' => $primary['oem_search_rows'] ?? null,
            ],
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

    /**
     * URL изображения детали (поле s3image и аналоги в ответах OEM / search-by-article).
     * Два лёгких запроса максимум — без полного обогащения.
     */
    public function resolveCatalogImageUrl(string $articleNo): ?string
    {
        $articleNo = trim($articleNo);
        if ($articleNo === '' || ! $this->isConfigured()) {
            return null;
        }

        $oemSearch = $this->searchByOemArticleNumber($articleNo);
        $oemList = is_array($oemSearch) && array_is_list($oemSearch) ? $oemSearch : [];
        $url = $this->extractS3ImageUrlFromRows($oemList);
        if ($url !== null) {
            return $url;
        }

        $search = $this->searchByArticleNumber($articleNo);
        $rows = $this->normalizeSearchRows($search);

        return $this->extractS3ImageUrlFromRows($rows);
    }

    /**
     * URL картинки: перебор вариантов номера (как {@see partNumberSearchCandidates}).
     */
    public function resolveCatalogImageUrlWithCandidates(string $skuRaw, ?string $alternateCode = null): ?string
    {
        foreach ($this->partNumberSearchCandidatesDetailed($skuRaw, $alternateCode) as $row) {
            if (! ($row['allowed'] ?? true)) {
                continue;
            }
            $candidate = (string) ($row['candidate'] ?? '');
            if ($candidate === '') {
                continue;
            }
            $url = $this->resolveCatalogImageUrl($candidate);
            if ($url !== null && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    protected function extractFirstArticleNameFromRows(array $rows): string
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['articleProductName', 'article_name', 'name', 'articleName'] as $key) {
                if (! isset($row[$key])) {
                    continue;
                }
                $name = trim((string) $row[$key]);
                if ($name !== '') {
                    return Str::limit($name, 255, '');
                }
            }
            if (isset($row['article']) && is_array($row['article'])) {
                $nested = $this->extractFirstArticleNameFromRows([$row['article']]);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    public function extractS3ImageUrlFromRows(array $rows): ?string
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = $this->extractS3ImageUrlFromArticleRow($row);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function extractS3ImageUrlFromArticleRow(array $row, int $depth = 0): ?string
    {
        if ($depth > 6) {
            return null;
        }

        foreach (['s3image', 's3Image', 's3_image', 'imageUrl', 'imageURL', 'image', 'thumbnailUrl', 'thumbUrl'] as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $v = $row[$key];
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '' && str_starts_with($v, 'http')) {
                    return $v;
                }
            }
        }

        foreach (['data', 'article', 'articleData', 'media', 'attributes'] as $nest) {
            if (isset($row[$nest]) && is_array($row[$nest])) {
                $inner = $this->extractS3ImageUrlFromArticleRow($row[$nest], $depth + 1);
                if ($inner !== null) {
                    return $inner;
                }
            }
        }

        return null;
    }

    protected function getJson(string $pathOrQuery): ?array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env (RapidAPI → Auto Parts Catalog → ключ).');
        }

        $base = rtrim((string) config('services.auto_parts_catalog.base_url'), '/');
        $url = str_starts_with($pathOrQuery, 'http') ? $pathOrQuery : $base.$pathOrQuery;
        $timeout = (int) config('services.auto_parts_catalog.timeout', 30);

        $req = Http::acceptJson()
            ->timeout($timeout)
            ->withHeaders([
                'X-RapidAPI-Key' => (string) config('services.auto_parts_catalog.key'),
                'X-RapidAPI-Host' => (string) config('services.auto_parts_catalog.host'),
            ]);
        $tls = $this->httpTlsOptionsForCatalog();
        if ($tls !== []) {
            $req = $req->withOptions($tls);
        }
        $response = $req->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Auto Parts Catalog HTTP '.$response->status().': '.$response->body()
            );
        }

        return $response->json();
    }

    /**
     * GET к каталогу без исключения при 404/ошибке сети (для перебора вариантов путей).
     */
    protected function tryGetJson(string $pathOrQuery): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $base = rtrim((string) config('services.auto_parts_catalog.base_url'), '/');
        $url = str_starts_with($pathOrQuery, 'http') ? $pathOrQuery : $base.$pathOrQuery;
        $timeout = (int) config('services.auto_parts_catalog.timeout', 30);

        try {
            $req = Http::acceptJson()
                ->timeout($timeout)
                ->withHeaders([
                    'X-RapidAPI-Key' => (string) config('services.auto_parts_catalog.key'),
                    'X-RapidAPI-Host' => (string) config('services.auto_parts_catalog.host'),
                ]);
            $tls = $this->httpTlsOptionsForCatalog();
            if ($tls !== []) {
                $req = $req->withOptions($tls);
            }
            $response = $req->get($url);
            if (! $response->successful()) {
                return null;
            }
            $json = $response->json();

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::debug('auto_parts_catalog_try_get_json', [
                'path' => $pathOrQuery,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Полный набор данных по OEM для записи в БД: категория, деталь со всеми фото,
     * применимость по конкретным ТС (интервалы выпуска), аналоги с пересечением по тем же машинам и полная применимость аналогов.
     *
     * @return array<string, mixed>
     */
    public function lookupFullOemBundleForPersistence(string $articleOemNo): array
    {
        $articleOemNo = trim($articleOemNo);
        $langId = (int) config('services.auto_parts_catalog.lang_id');

        $oemSearch = $this->searchByOemArticleNumber($articleOemNo);
        $oemRows = is_array($oemSearch) && array_is_list($oemSearch) ? $oemSearch : [];
        $first = $oemRows[0] ?? null;
        $firstArr = is_array($first) ? $first : [];

        $articleId = isset($firstArr['articleId']) && is_numeric($firstArr['articleId'])
            ? (int) $firstArr['articleId']
            : null;

        $categoryRaw = $articleId !== null ? $this->getCategoryByArticleId($articleId) : null;
        $categoryParts = $this->splitCategoryLevels($categoryRaw);

        $rawApplicability = $this->collectOemRawApplicabilityRows($articleOemNo, $oemRows);
        $vehiclesDetailed = [];
        $seenVeh = [];
        foreach ($rawApplicability as $r) {
            if (! is_array($r)) {
                continue;
            }
            $norm = $this->normalizeVehicleApplicabilityForDb($r);
            $fp = $this->vehicleApplicabilityFingerprint($norm);
            if ($fp === '' || isset($seenVeh[$fp])) {
                continue;
            }
            $seenVeh[$fp] = true;
            $vehiclesDetailed[] = $norm;
        }

        $oemIndex = $this->buildOemApplicabilityIndex($rawApplicability);

        $primaryDetail = $articleId !== null ? $this->tryFetchArticleDetailsPayload($articleId, $langId) : null;
        $primaryArticleInfo = [];
        if (is_array($primaryDetail)) {
            if (isset($primaryDetail['articleInfo']) && is_array($primaryDetail['articleInfo'])) {
                $primaryArticleInfo = $primaryDetail['articleInfo'];
            } elseif (isset($primaryDetail['article']) && is_array($primaryDetail['article'])) {
                $primaryArticleInfo = $primaryDetail['article'];
            }
        }

        $partArticleNo = $this->pickFirstString([
            $firstArr['articleNo'] ?? null,
            $primaryArticleInfo['articleNo'] ?? null,
        ]);
        $partName = $this->pickFirstString([
            $firstArr['articleProductName'] ?? null,
            $primaryArticleInfo['articleProductName'] ?? null,
        ]);
        $supplierId = $this->pickFirstInt([
            $firstArr['supplierId'] ?? null,
            $primaryArticleInfo['supplierId'] ?? null,
        ]);
        $supplierName = $this->pickFirstString([
            $firstArr['supplierName'] ?? null,
            $primaryArticleInfo['supplierName'] ?? null,
        ]);

        $specifications = $this->normalizePartSpecifications(
            isset($primaryArticleInfo['allSpecifications']) && is_array($primaryArticleInfo['allSpecifications'])
                ? $primaryArticleInfo['allSpecifications']
                : []
        );
        if ($specifications === [] && isset($firstArr['allSpecifications']) && is_array($firstArr['allSpecifications'])) {
            $specifications = $this->normalizePartSpecifications($firstArr['allSpecifications']);
        }

        $oemNumbers = $this->normalizePartOemNumbers(
            isset($primaryArticleInfo['oemNo']) && is_array($primaryArticleInfo['oemNo'])
                ? $primaryArticleInfo['oemNo']
                : (isset($firstArr['oemNo']) && is_array($firstArr['oemNo']) ? $firstArr['oemNo'] : [])
        );

        if ($specifications === [] && is_array($primaryDetail)) {
            foreach (['articleAllSpecifications', 'allSpecifications'] as $specKey) {
                if (isset($primaryDetail[$specKey]) && is_array($primaryDetail[$specKey])) {
                    $specifications = $this->normalizePartSpecifications($primaryDetail[$specKey]);
                    if ($specifications !== []) {
                        break;
                    }
                }
            }
        }
        if ($oemNumbers === [] && is_array($primaryDetail)) {
            foreach (['articleOemNo', 'oemNo'] as $oemKey) {
                if (isset($primaryDetail[$oemKey]) && is_array($primaryDetail[$oemKey])) {
                    $oemNumbers = $this->normalizePartOemNumbers($primaryDetail[$oemKey]);
                    if ($oemNumbers !== []) {
                        break;
                    }
                }
            }
        }

        $imageUrls = $this->collectAllImageUrlsForArticle($articleId, $firstArr, $primaryDetail);
        $modelYears = $this->getModelYearsByOem($articleOemNo);

        $crossRaw = $this->getCrossReferencesByOemArticleNumber($articleOemNo);
        $articles = $this->extractArtlookupArticlesArray($crossRaw);

        $analogsReplacement = [];
        $seenAnalog = [];

        foreach ($articles as $art) {
            if (! is_array($art)) {
                continue;
            }
            $aid = isset($art['articleId']) && is_numeric($art['articleId']) ? (int) $art['articleId'] : null;
            if ($aid === null || ($articleId !== null && $aid === $articleId)) {
                continue;
            }

            $cars = $this->extractCompatibleCarsFromArticleLikePayload($art);
            if ($cars === []) {
                $detail = $this->tryFetchArticleDetailsPayload($aid, $langId);
                $cars = $this->extractCompatibleCarsFromArticleLikePayload($detail ?? []);
                if ($cars === []) {
                    $cars = $this->extractCompatibleCarsFromArticlesBulkResponse($detail ?? [], $aid);
                }
            }
            if ($cars === []) {
                $an = trim((string) ($art['articleNo'] ?? ''));
                $sid = isset($art['supplierId']) && is_numeric($art['supplierId']) ? (int) $art['supplierId'] : null;
                if ($an !== '') {
                    $bulk = $this->tryGetCompatibleCarsByArticleNumber($an, $sid, $langId);
                    $cars = $this->extractCompatibleCarsFromArticlesBulkResponse($bulk, $aid);
                }
            }

            $matchesSame = [];
            foreach ($cars as $c) {
                if (! is_array($c)) {
                    continue;
                }
                if ($this->compatibleCarMatchesOemIndex($c, $oemIndex)) {
                    $matchesSame[] = $this->normalizeVehicleApplicabilityForDb($c);
                }
            }
            if ($matchesSame === []) {
                continue;
            }

            if (isset($seenAnalog[$aid])) {
                continue;
            }
            $seenAnalog[$aid] = true;

            $allCarsNorm = [];
            foreach ($cars as $c) {
                if (is_array($c)) {
                    $allCarsNorm[] = $this->normalizeVehicleApplicabilityForDb($c);
                }
            }

            $analogDetail = $this->tryFetchArticleDetailsPayload($aid, $langId);
            $analogsReplacement[] = [
                'article_id' => $aid,
                'supplier_name' => trim((string) ($art['supplierName'] ?? '')),
                'article_no' => trim((string) ($art['articleNo'] ?? '')),
                'name' => trim((string) ($art['articleProductName'] ?? '')),
                'image_urls' => $this->collectAllImageUrlsForArticle($aid, $art, $analogDetail),
                'fits_same_oem_vehicle_applicability' => $matchesSame,
                'compatibility_all' => $allCarsNorm,
            ];
        }

        $main = $categoryParts['main'];
        $sub = $categoryParts['sub'];
        $full = trim($main.($sub !== '' ? ' > '.$sub : ''));

        return [
            'oem' => $articleOemNo,
            'locale' => 'ru',
            'category' => [
                'main' => $main,
                'sub' => $sub,
                'full' => $full,
            ],
            'part' => [
                'article_id' => $articleId,
                'article_no' => $partArticleNo,
                'name' => $partName,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'image_urls' => $imageUrls,
                'image_url_primary' => $imageUrls[0] ?? '',
                'specifications' => $specifications,
                'oem_numbers' => $oemNumbers,
            ],
            'compatibility' => [
                'vehicles' => $vehiclesDetailed,
                'models_summary' => $modelYears,
            ],
            'analogs_replacement_same_applicability' => $analogsReplacement,
            'meta' => [
                'artlookup_count_articles' => isset($crossRaw['countArticles']) && is_numeric($crossRaw['countArticles'])
                    ? (int) $crossRaw['countArticles']
                    : null,
                'artlookup_articles_total' => count($articles),
                'analogs_with_same_vehicle_match' => count($analogsReplacement),
            ],
            'source_payload' => [
                'category_raw' => $categoryRaw,
                'oem_search_rows' => $oemRows,
                'primary_article_detail_raw' => $primaryDetail,
            ],
        ];
    }

    /**
     * Преобразует результат {@see lookupFullOemBundleForPersistence} в формат {@see lookupEnrichedForStock}
     * для повторного использования без запросов к API (импорт из JSONL).
     *
     * @param  array<string, mixed>  $bundle
     * @return array{
     *   source: 'oem'|'none',
     *   category_main: string,
     *   category_sub: string,
     *   category_raw: array<string, mixed>|null,
     *   oem_suppliers: list<array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>,
     *   cross_analogs: list<array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>,
     *   vehicles_normalized: list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>,
     *   vehicles_raw: null,
     *   first_article_id: int|null,
     *   first_article_name: string,
     *   manufacturer_id: int|null,
     *   catalog_image_url: string,
     * }
     */
    public function enrichmentPayloadFromFullOemBundle(array $bundle): array
    {
        $payload = $bundle['source_payload'] ?? null;
        $oemRows = is_array($payload) && isset($payload['oem_search_rows']) && is_array($payload['oem_search_rows'])
            ? $payload['oem_search_rows']
            : [];
        if (! array_is_list($oemRows) || $oemRows === []) {
            return $this->emptyEnrichmentPayload();
        }

        $cat = $bundle['category'] ?? null;
        $main = '';
        $sub = '';
        if (is_array($cat)) {
            $main = trim((string) ($cat['main'] ?? ''));
            $sub = trim((string) ($cat['sub'] ?? ''));
        }

        $categoryRaw = is_array($payload) && isset($payload['category_raw']) && is_array($payload['category_raw'])
            ? $payload['category_raw']
            : null;

        $vehNorm = [];
        $compat = $bundle['compatibility'] ?? null;
        $vehList = is_array($compat) && isset($compat['vehicles']) && is_array($compat['vehicles'])
            ? $compat['vehicles']
            : [];
        foreach ($vehList as $v) {
            if (! is_array($v)) {
                continue;
            }
            $make = trim((string) ($v['make'] ?? ''));
            $model = trim((string) ($v['model'] ?? ''));
            if ($make === '' || $model === '') {
                continue;
            }
            $yf = $v['year_from'] ?? null;
            $yt = $v['year_to'] ?? null;
            $vehNorm[] = [
                'make' => $make,
                'model' => $model,
                'body_type' => trim((string) ($v['body_type'] ?? '')),
                'year_from' => is_numeric($yf) ? (int) $yf : null,
                'year_to' => is_numeric($yt) ? (int) $yt : null,
                'engine' => trim((string) ($v['engine'] ?? '')),
            ];
        }

        $crossAnalogs = [];
        $analogs = $bundle['analogs_replacement_same_applicability'] ?? null;
        if (is_array($analogs) && array_is_list($analogs)) {
            foreach ($analogs as $a) {
                if (! is_array($a)) {
                    continue;
                }
                $an = trim((string) ($a['article_no'] ?? ''));
                if ($an === '') {
                    continue;
                }
                $sn = trim((string) ($a['supplier_name'] ?? ''));
                $crossAnalogs[] = [
                    'supplierName' => $sn,
                    'articleNo' => $an,
                    'crossManufacturerName' => $sn !== '' ? $sn : null,
                    'crossNumber' => $an,
                    'searchLevel' => 'oem_bundle',
                ];
            }
        }

        $part = $bundle['part'] ?? [];
        $part = is_array($part) ? $part : [];
        $urls = isset($part['image_urls']) && is_array($part['image_urls']) ? $part['image_urls'] : [];
        $primary = trim((string) ($part['image_url_primary'] ?? ''));
        if ($primary === '' && $urls !== []) {
            $first = $urls[0] ?? null;
            $primary = is_string($first) ? trim($first) : '';
        }

        $firstArticleId = isset($part['article_id']) && is_numeric($part['article_id'])
            ? (int) $part['article_id']
            : null;

        $firstOem = $oemRows[0] ?? null;
        $manufacturerId = null;
        if (is_array($firstOem) && isset($firstOem['manufacturerId']) && is_numeric($firstOem['manufacturerId'])) {
            $manufacturerId = (int) $firstOem['manufacturerId'];
        }

        return [
            'source' => 'oem',
            'category_main' => $main,
            'category_sub' => $sub,
            'category_raw' => $categoryRaw,
            'oem_suppliers' => $this->uniqueSuppliersFromOemRows($oemRows),
            'cross_analogs' => $crossAnalogs,
            'vehicles_normalized' => $vehNorm,
            'vehicles_raw' => null,
            'first_article_id' => $firstArticleId,
            'first_article_name' => trim((string) ($part['name'] ?? '')),
            'manufacturer_id' => $manufacturerId,
            'catalog_image_url' => $primary,
        ];
    }

    /**
     * @param  array<int, mixed>  $oemSearchRows
     * @return list<array<string, mixed>>
     */
    protected function collectOemRawApplicabilityRows(string $articleOemNo, array $oemSearchRows): array
    {
        $manufacturerIds = [];
        foreach ($oemSearchRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mid = $row['manufacturerId'] ?? null;
            if (is_numeric($mid)) {
                $manufacturerIds[(int) $mid] = true;
            }
        }
        $manufacturerIds = array_keys($manufacturerIds);
        if ($manufacturerIds === []) {
            return [];
        }

        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $oemEnc = rawurlencode($articleOemNo);

        $paths = [];
        foreach ($manufacturerIds as $manufacturerId) {
            $paths['v_'.$manufacturerId] = '/articles-oem/selecting-a-list-of-cars-for-oem-part-number'
                ."/type-id/{$typeId}/lang-id/{$langId}/country-filter-id/{$countryId}"
                ."/manufacturer-id/{$manufacturerId}/article-oem-no/{$oemEnc}";
        }

        $responses = $this->rapidApiPoolGet($paths);
        $all = [];
        foreach ($responses as $payload) {
            if (! is_array($payload)) {
                continue;
            }
            $vehicleRows = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
            if (! is_array($vehicleRows) || ! array_is_list($vehicleRows)) {
                continue;
            }
            foreach ($vehicleRows as $vehicleRow) {
                if (is_array($vehicleRow)) {
                    $all[] = $vehicleRow;
                }
            }
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>|null  $crossRaw
     * @return array<int, mixed>
     */
    protected function extractArtlookupArticlesArray(?array $crossRaw): array
    {
        if ($crossRaw === null || ! isset($crossRaw['articles']) || ! is_array($crossRaw['articles'])) {
            return [];
        }
        $articles = $crossRaw['articles'];

        return array_is_list($articles) ? $articles : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    protected function extractCompatibleCarsFromArticleLikePayload(array $payload): array
    {
        foreach (['compatibleCars', 'compatible_cars', 'vehicles', 'vehicleList'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) {
                return $payload[$key];
            }
        }
        if (isset($payload['articleInfo']) && is_array($payload['articleInfo'])) {
            $inner = $this->extractCompatibleCarsFromArticleLikePayload($payload['articleInfo']);
            if ($inner !== []) {
                return $inner;
            }
        }

        return [];
    }

    /**
     * Из ответа вида countArticles + articles[], где у элемента есть compatibleCars (плейграунд RapidAPI, эндпоинт get-compatible-cars-by-article-number).
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<int, mixed>
     */
    protected function extractCompatibleCarsFromArticlesBulkResponse(?array $payload, ?int $preferArticleId = null): array
    {
        if ($payload === null || ! isset($payload['articles']) || ! is_array($payload['articles'])) {
            return [];
        }
        $articles = $payload['articles'];
        if (! array_is_list($articles)) {
            return [];
        }
        $fallback = [];
        foreach ($articles as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cars = $this->extractCompatibleCarsFromArticleLikePayload($row);
            if ($cars === []) {
                continue;
            }
            if ($preferArticleId !== null && isset($row['articleId']) && is_numeric($row['articleId']) && (int) $row['articleId'] === $preferArticleId) {
                return $cars;
            }
            if ($fallback === []) {
                $fallback = $cars;
            }
        }

        return $fallback;
    }

    /**
     * Пути из .env: RAPIDAPI_AUTO_PARTS_ARTICLE_DETAIL_PATHS_EXTRA через «|», плейсхолдеры {articleId}, {langId}.
     *
     * @return list<string>
     */
    protected function articleDetailPathExtraFromConfig(int $articleId, int $langId): array
    {
        $raw = trim((string) config('services.auto_parts_catalog.article_detail_paths_extra', ''));
        if ($raw === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/\|+/', $raw) ?: [] as $chunk) {
            $p = trim($chunk);
            if ($p === '') {
                continue;
            }
            $p = str_replace(
                ['{articleId}', '{langId}'],
                [(string) $articleId, (string) $langId],
                $p
            );
            if (! str_starts_with($p, '/')) {
                $p = '/'.$p;
            }
            $out[] = $p;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function articleDetailPayloadHasUsableArticleInfo(array $json): bool
    {
        $info = $json['articleInfo'] ?? $json['article'] ?? null;
        if (! is_array($info)) {
            return false;
        }
        $specs = $info['allSpecifications'] ?? null;
        if (is_array($specs) && $specs !== []) {
            return true;
        }
        $oem = $info['oemNo'] ?? null;

        return is_array($oem) && $oem !== [];
    }

    /**
     * «Достаточный» ответ: либо применимость к авто, либо нормальная карточка (характеристики / OEM-номера).
     *
     * @param  array<string, mixed>  $json
     */
    protected function articleDetailPayloadIsAdequate(array $json): bool
    {
        if ($this->extractCompatibleCarsFromArticleLikePayload($json) !== []) {
            return true;
        }
        if ($this->extractCompatibleCarsFromArticlesBulkResponse($json, null) !== []) {
            return true;
        }

        return $this->articleDetailPayloadHasUsableArticleInfo($json);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function scoreArticleDetailPayload(array $json): int
    {
        $score = 0;
        $cars = $this->extractCompatibleCarsFromArticleLikePayload($json);
        if ($cars === []) {
            $cars = $this->extractCompatibleCarsFromArticlesBulkResponse($json, null);
        }
        if ($cars !== []) {
            $score += 80 + min(count($cars), 40);
        }
        if ($this->articleDetailPayloadHasUsableArticleInfo($json)) {
            $score += 50;
        }
        if (isset($json['articleInfo']) && is_array($json['articleInfo'])) {
            $score += 5;
        }
        if (isset($json['articleNo']) && is_string($json['articleNo']) && trim($json['articleNo']) !== '') {
            $score += 2;
        }
        if (isset($json['eanNo']) || isset($json['eanNumbers'])) {
            $score += 3;
        }

        return $score;
    }

    /**
     * @return list<string>
     */
    protected function articleDetailFetchCandidatePaths(int $articleId, int $langId): array
    {
        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $builtIn = [
            "/articles/details/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/article-complete-details/type-id/{$typeId}/article-id/{$articleId}/lang-id/{$langId}/country-filter-id/{$countryId}",
            "/articles/get-article-all-information/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-all-article-information/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-all-informations/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-all-informing/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-all-article-informing/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-full-details/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-all-details/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-details/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-by-article-id/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-by-id/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/article-details/article-id/{$articleId}/lang-id/{$langId}",
        ];
        $merged = array_merge(
            $this->articleDetailPathExtraFromConfig($articleId, $langId),
            $builtIn
        );
        $seen = [];
        $uniq = [];
        foreach ($merged as $p) {
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $uniq[] = $p;
        }

        return $uniq;
    }

    /**
     * Подбор JSON карточки артикула: несколько возможных маршрутов RapidAPI, приоритет ответам с compatibleCars и articleInfo.
     *
     * @return array<string, mixed>|null
     */
    protected function tryFetchArticleDetailsPayload(int $articleId, ?int $langId = null): ?array
    {
        $langId ??= (int) config('services.auto_parts_catalog.lang_id');
        $candidates = $this->articleDetailFetchCandidatePaths($articleId, $langId);

        $best = null;
        $bestScore = -1;
        foreach ($candidates as $path) {
            $json = $this->tryGetJson($path);
            if ($json === null || $json === []) {
                continue;
            }
            if ($this->articleDetailPayloadIsAdequate($json)) {
                return $json;
            }
            $sc = $this->scoreArticleDetailPayload($json);
            if ($sc > $bestScore) {
                $bestScore = $sc;
                $best = $json;
            }
        }

        return $best;
    }

    /**
     * GET /articles/get-compatible-cars-by-article-number/type-id/…/article-no/… — в articles[] приходит compatibleCars (как в плейграунде c0ea4c09…).
     *
     * @return array<string, mixed>|null
     */
    protected function tryGetCompatibleCarsByArticleNumber(string $articleNo, ?int $supplierId, ?int $langId = null): ?array
    {
        $articleNo = trim($articleNo);
        if ($articleNo === '' || ! $this->isConfigured()) {
            return null;
        }
        $langId ??= (int) config('services.auto_parts_catalog.lang_id');
        $typeId = (int) config('services.auto_parts_catalog.vehicle_type_id');
        $countryId = (int) config('services.auto_parts_catalog.country_filter_id');
        $enc = rawurlencode($articleNo);
        $paths = [];
        if ($supplierId !== null && $supplierId > 0) {
            $paths[] = '/articles/get-compatible-cars-by-article-number/type-id/'.$typeId
                .'/article-no/'.$enc.'/supplier-id/'.$supplierId
                .'/lang-id/'.$langId.'/country-filter-id/'.$countryId;
        }
        $paths[] = '/articles/get-compatible-cars-by-article-number/type-id/'.$typeId
            .'/article-no/'.$enc.'/lang-id/'.$langId.'/country-filter-id/'.$countryId;

        foreach ($paths as $path) {
            $json = $this->tryGetJson($path);
            if ($json === null || $json === []) {
                continue;
            }
            if (isset($json['articles']) && is_array($json['articles']) && $json['articles'] !== []) {
                return $json;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function tryFetchArticleMediaUrlList(int $articleId, ?int $langId = null): array
    {
        $langId ??= (int) config('services.auto_parts_catalog.lang_id');
        $candidates = [
            "/articles/get-article-all-media/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-all-article-media/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/get-article-media/article-id/{$articleId}/lang-id/{$langId}",
            "/articles/article-media/article-id/{$articleId}/lang-id/{$langId}",
        ];
        foreach ($candidates as $path) {
            $json = $this->tryGetJson($path);
            if ($json === null) {
                continue;
            }
            $urls = $this->extractHttpUrlsFromMediaPayload($json);
            if ($urls !== []) {
                return $urls;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $detailPayload
     * @return list<string>
     */
    protected function collectAllImageUrlsForArticle(?int $articleId, array $oemOrArticleRow, ?array $detailPayload = null): array
    {
        $urls = [];
        $push = function (string $u) use (&$urls): void {
            $u = trim($u);
            if ($u === '' || ! str_starts_with($u, 'http')) {
                return;
            }
            foreach ($urls as $existing) {
                if ($existing === $u) {
                    return;
                }
            }
            $urls[] = $u;
        };

        foreach ($this->extractHttpUrlsFromMediaPayload($oemOrArticleRow) as $u) {
            $push($u);
        }
        if ($detailPayload !== null) {
            foreach ($this->extractHttpUrlsFromMediaPayload($detailPayload) as $u) {
                $push($u);
            }
        }
        if ($articleId !== null) {
            foreach ($this->tryFetchArticleMediaUrlList($articleId) as $u) {
                $push($u);
            }
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    protected function extractHttpUrlsFromMediaPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }
        $out = [];
        $walk = function (mixed $node, int $depth) use (&$walk, &$out): void {
            if ($depth > 14) {
                return;
            }
            if (is_string($node)) {
                $t = trim($node);
                if (str_starts_with($t, 'http') && (str_contains($t, 'webp') || str_contains($t, 'jpg') || str_contains($t, 'jpeg') || str_contains($t, 'png'))) {
                    $out[] = $t;
                }

                return;
            }
            if (! is_array($node)) {
                return;
            }
            foreach (['s3image', 's3Image', 's3_image', 'imageUrl', 'imageURL', 'url'] as $k) {
                if (isset($node[$k]) && is_string($node[$k])) {
                    $t = trim($node[$k]);
                    if (str_starts_with($t, 'http')) {
                        $out[] = $t;
                    }
                }
            }
            foreach ($node as $v) {
                if (is_array($v)) {
                    $walk($v, $depth + 1);
                }
            }
        };
        $walk($payload, 0);

        $uniq = [];
        $ordered = [];
        foreach ($out as $u) {
            if (isset($uniq[$u])) {
                continue;
            }
            $uniq[$u] = true;
            $ordered[] = $u;
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalizeVehicleApplicabilityForDb(array $row): array
    {
        $make = $this->pickStringFrom($row, [
            'vehicleManufacturerName',
            'manufacturerName',
            'manuName',
            'makeName',
        ]);
        $model = $this->pickStringFrom($row, [
            'vehicleModelName',
            'modelName',
            'modelNameShort',
        ]);
        $body = $this->pickStringFrom($row, [
            'bodyType',
            'constructionTypeName',
            'bodyTypeName',
            'bodyTypeNameShort',
            'vehicleBodyTypeName',
        ]);
        $typeEngine = $this->pickStringFrom($row, [
            'typeEngineName',
            'engineType',
        ]);
        $engineCore = $this->formatEngineFromRow($row);
        $engine = trim(
            $typeEngine !== ''
                ? $typeEngine.($engineCore !== '' ? ', '.$engineCore : '')
                : $engineCore,
            " \t\n\r\0\x0B,"
        );

        $cStart = $this->pickStringFrom($row, ['constructionIntervalStart', 'constructionFrom']);
        $cEnd = $this->pickStringFrom($row, ['constructionIntervalEnd', 'constructionTo']);

        $yearFrom = $this->pickIntFrom($row, ['yearOfConstructionFrom', 'yearFrom', 'constructionFrom']);
        $yearTo = $this->pickIntFrom($row, ['yearOfConstructionTo', 'yearTo', 'constructionTo']);
        if ($yearFrom === null && $cStart !== '') {
            $yearFrom = $this->extractYearFromDateString($cStart);
        }
        if ($yearTo === null && $cEnd !== '') {
            $yearTo = $this->extractYearFromDateString($cEnd);
        }

        $vehicleId = isset($row['vehicleId']) && is_numeric($row['vehicleId']) ? (int) $row['vehicleId'] : null;
        $modelId = isset($row['modelId']) && is_numeric($row['modelId']) ? (int) $row['modelId'] : null;

        return [
            'vehicle_id' => $vehicleId,
            'model_id' => $modelId,
            'make' => $make,
            'model' => $model,
            'body_type' => $body,
            'type_engine_name' => $typeEngine,
            'engine' => $engine,
            'construction_interval_start' => $cStart !== '' ? $cStart : null,
            'construction_interval_end' => $cEnd !== '' ? $cEnd : null,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
        ];
    }

    protected function extractYearFromDateString(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{4})/', $value, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $norm
     */
    protected function vehicleApplicabilityFingerprint(array $norm): string
    {
        $parts = [
            (string) ($norm['vehicle_id'] ?? ''),
            mb_strtolower((string) ($norm['make'] ?? '')),
            mb_strtolower((string) ($norm['model'] ?? '')),
            mb_strtolower((string) ($norm['body_type'] ?? '')),
            mb_strtolower((string) ($norm['type_engine_name'] ?? '')),
            (string) ($norm['construction_interval_start'] ?? ''),
            (string) ($norm['construction_interval_end'] ?? ''),
        ];

        return implode("\0", $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rawRows
     * @return array{vehicle_ids: list<int>, intervals: list<array{make: string, model: string, body: string, engine: string, start: Carbon|null, end: Carbon|null}>}
     */
    protected function buildOemApplicabilityIndex(array $rawRows): array
    {
        $vehicleIds = [];
        $intervals = [];
        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (isset($row['vehicleId']) && is_numeric($row['vehicleId'])) {
                $vehicleIds[] = (int) $row['vehicleId'];
            }
            $make = mb_strtolower($this->pickStringFrom($row, ['vehicleManufacturerName', 'manufacturerName', 'manuName']));
            $model = mb_strtolower($this->pickStringFrom($row, ['vehicleModelName', 'modelName']));
            $body = mb_strtolower($this->pickStringFrom($row, ['bodyType', 'constructionTypeName', 'bodyTypeName', 'vehicleBodyTypeName']));
            $engine = mb_strtolower($this->pickStringFrom($row, ['typeEngineName', 'engineType']));
            $s = $this->pickStringFrom($row, ['constructionIntervalStart', 'constructionFrom']);
            $e = $this->pickStringFrom($row, ['constructionIntervalEnd', 'constructionTo']);
            $start = $this->tryParseCatalogDate($s);
            $end = $this->tryParseCatalogDate($e);
            if ($make === '' && $model === '') {
                continue;
            }
            $intervals[] = [
                'make' => $make,
                'model' => $model,
                'body' => $body,
                'engine' => $engine,
                'start' => $start,
                'end' => $end,
            ];
        }

        $vehicleIds = array_values(array_unique($vehicleIds));

        return [
            'vehicle_ids' => $vehicleIds,
            'intervals' => $intervals,
        ];
    }

    protected function tryParseCatalogDate(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $car
     * @param  array{vehicle_ids: list<int>, intervals: list<array<string, mixed>>}  $oemIndex
     */
    protected function compatibleCarMatchesOemIndex(array $car, array $oemIndex): bool
    {
        if (isset($car['vehicleId']) && is_numeric($car['vehicleId'])) {
            $vid = (int) $car['vehicleId'];
            foreach ($oemIndex['vehicle_ids'] as $allowed) {
                if ($vid === $allowed) {
                    return true;
                }
            }
        }

        $cMake = mb_strtolower($this->pickStringFrom($car, ['vehicleManufacturerName', 'manufacturerName', 'manuName']));
        $cModel = mb_strtolower($this->pickStringFrom($car, ['vehicleModelName', 'modelName']));
        $cBody = mb_strtolower($this->pickStringFrom($car, ['bodyType', 'constructionTypeName', 'bodyTypeName', 'vehicleBodyTypeName']));
        $cEngine = mb_strtolower($this->pickStringFrom($car, ['typeEngineName', 'engineType']));
        $s = $this->pickStringFrom($car, ['constructionIntervalStart', 'constructionFrom']);
        $e = $this->pickStringFrom($car, ['constructionIntervalEnd', 'constructionTo']);
        $cStart = $this->tryParseCatalogDate($s);
        $cEnd = $this->tryParseCatalogDate($e);

        foreach ($oemIndex['intervals'] as $slot) {
            /** @var Carbon|null $oStart */
            $oStart = $slot['start'] ?? null;
            /** @var Carbon|null $oEnd */
            $oEnd = $slot['end'] ?? null;
            if (($slot['make'] ?? '') !== '' && $cMake !== '' && $slot['make'] !== $cMake) {
                continue;
            }
            if (($slot['model'] ?? '') !== '' && $cModel !== '' && $slot['model'] !== $cModel) {
                continue;
            }
            if (($slot['body'] ?? '') !== '' && $cBody !== '' && $slot['body'] !== $cBody) {
                continue;
            }
            if (($slot['engine'] ?? '') !== '' && $cEngine !== '' && $slot['engine'] !== $cEngine) {
                continue;
            }
            if ($this->constructionIntervalsOverlap($oStart, $oEnd, $cStart, $cEnd)) {
                return true;
            }
        }

        return false;
    }

    protected function constructionIntervalsOverlap(
        ?Carbon $aStart,
        ?Carbon $aEnd,
        ?Carbon $bStart,
        ?Carbon $bEnd,
    ): bool {
        if ($aStart === null && $bStart === null) {
            return false;
        }
        $aS = $aStart ?? Carbon::create(1900, 1, 1);
        $aE = $aEnd ?? Carbon::create(2100, 12, 31);
        $bS = $bStart ?? Carbon::create(1900, 1, 1);
        $bE = $bEnd ?? Carbon::create(2100, 12, 31);
        if ($aS->gt($aE)) {
            [$aS, $aE] = [$aE, $aS];
        }
        if ($bS->gt($bE)) {
            [$bS, $bE] = [$bE, $bS];
        }

        return ! $aE->lt($bS) && ! $bE->lt($aS);
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
     *   first_article_name: string,
     *   manufacturer_id: int|null,
     *   catalog_image_url: string,
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

            $imageUrl = $this->extractS3ImageUrlFromRows($oemList);

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
                'first_article_name' => $this->extractFirstArticleNameFromRows($oemList),
                'manufacturer_id' => $lookup['manufacturer_id'] ?? null,
                'catalog_image_url' => $imageUrl ?? '',
            ];
        }

        $search = $this->searchByArticleNumber($articleNo);
        $rows = $this->normalizeSearchRows($search);
        $firstId = $this->firstArticleId($rows);

        $langId = (int) config('services.auto_parts_catalog.lang_id');
        $oemEnc = rawurlencode($articleNo);
        $pathsArticle = [
            'cross_oem' => '/artlookup/search-for-analogue-of-spare-parts-by-oem-number/article-oem-no/'.$oemEnc,
        ];
        if ($firstId !== null) {
            $pathsArticle['category'] = "/articles/get-article-category/article-id/{$firstId}/lang-id/{$langId}";
            $pathsArticle['cross_aid'] = '/artlookup/select-article-cross-references/article-id/'.$firstId.'/lang-id/'.$langId;
        }
        $pooledArticle = $this->rapidApiPoolGet($pathsArticle);

        $categoryRaw = isset($pooledArticle['category']) && is_array($pooledArticle['category'])
            ? $pooledArticle['category']
            : null;
        $parts = $this->splitCategoryLevels($categoryRaw);
        $articleSuppliers = $this->uniqueSuppliersFromOemRows($rows);

        $crossRawArticle = $pooledArticle['cross_oem'] ?? null;
        $articlesOem = (is_array($crossRawArticle) && isset($crossRawArticle['articles']) && is_array($crossRawArticle['articles']))
            ? $crossRawArticle['articles']
            : [];
        $crossAnalogs = $this->uniqueAnalogRowsFromCrossReferenceArticles($articlesOem, true);
        if ($firstId !== null) {
            $crossAnalogs = $this->mergeUniqueCrossAnalogRows(
                $crossAnalogs,
                $this->parseArticleIdCrossReferencePayload($pooledArticle['cross_aid'] ?? null)
            );
        }

        $hasCategory = $parts['main'] !== '' || $parts['sub'] !== '';
        $hasAnything = $firstId !== null || $hasCategory || $articleSuppliers !== [] || $crossAnalogs !== [];

        if (! $hasAnything) {
            return $this->emptyEnrichmentPayload();
        }

        $imageUrl = $this->extractS3ImageUrlFromRows($rows);

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
            'first_article_name' => $this->extractFirstArticleNameFromRows($rows),
            'manufacturer_id' => null,
            'catalog_image_url' => $imageUrl ?? '',
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
     *   first_article_name: string,
     *   manufacturer_id: null,
     *   catalog_image_url: string,
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
            'first_article_name' => '',
            'manufacturer_id' => null,
            'catalog_image_url' => '',
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

    /**
     * @param  array<int, array<string, mixed>>  $vehicleRows
     * @return list<array{
     *   modelId: int|null,
     *   modelName: string,
     *   modelYearFrom: string|null,
     *   modelYearTo: string|null
     * }>
     */
    protected function deduplicateModelsWithYears(array $vehicleRows): array
    {
        $out = [];
        $seen = [];

        foreach ($vehicleRows as $row) {
            $modelIdRaw = $row['modelId'] ?? $row['vehicleModelId'] ?? $row['model_id'] ?? null;
            $modelId = is_numeric($modelIdRaw) ? (int) $modelIdRaw : null;

            $modelName = trim((string) ($row['modelName'] ?? $row['vehicleModelName'] ?? ''));
            if ($modelName === '') {
                continue;
            }

            $yearFrom = $this->normalizeYearDate($row['modelYearFrom'] ?? $row['yearOfConstructionFrom'] ?? $row['yearFrom'] ?? null);
            $yearTo = $this->normalizeYearDate($row['modelYearTo'] ?? $row['yearOfConstructionTo'] ?? $row['yearTo'] ?? null);

            $key = ($modelId !== null ? (string) $modelId : 'null')."\0".$modelName."\0".($yearFrom ?? '')."\0".($yearTo ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'modelId' => $modelId,
                'modelName' => $modelName,
                'modelYearFrom' => $yearFrom,
                'modelYearTo' => $yearTo,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcasecmp($a['modelName'], $b['modelName']));

        return $out;
    }

    protected function normalizeYearDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $year = (int) $value;
            if ($year >= 1900 && $year <= 2999) {
                return sprintf('%04d-01-01', $year);
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $trimmed) === 1) {
            return $trimmed.'-01-01';
        }

        return $trimmed;
    }

    /**
     * @param  list<mixed>  $values
     */
    protected function pickFirstString(array $values): string
    {
        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $s = trim((string) $value);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }

    /**
     * @param  list<mixed>  $values
     */
    protected function pickFirstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{name: string, value: string}>
     */
    protected function normalizePartSpecifications(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['criteriaName'] ?? ''));
            $value = trim((string) ($row['criteriaValue'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{brand: string, number: string}>
     */
    protected function normalizePartOemNumbers(array $rows): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $brand = trim((string) ($row['oemBrand'] ?? ''));
            $number = trim((string) ($row['oemDisplayNo'] ?? ''));
            if ($brand === '' || $number === '') {
                continue;
            }
            $key = $brand."\0".$number;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                'brand' => $brand,
                'number' => $number,
            ];
        }

        return $out;
    }

    protected function compactPartNumber(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $t = preg_replace('/[\s\-_\.\/]+/u', '', $s);

        return Str::limit(is_string($t) ? $t : $s, 100, '');
    }

    protected function alnumOnlyPartNumber(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $t = preg_replace('/[^A-Za-z0-9]/u', '', $s);

        return Str::limit(is_string($t) ? $t : '', 100, '');
    }
}
