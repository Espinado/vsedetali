<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\TecDocCatalogService;
use App\Support\CatalogStorefrontCategoryConflictDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Сверка «чистой» TecDoc-категории (RapidAPI ronhartman/tecdoc-catalog) с тем, что назначено у товара на витрине.
 *
 * Первый запуск — только отчёт (без записи в БД). Источник товаров на выбор:
 *   - список SKU/OEM через аргумент {@see $oems} или --sku;
 *   - товары, подозрительные по текущей эвристике {@see CatalogStorefrontCategoryConflictDetector} (--suspects);
 *   - первые N активных товаров (--limit), если ничего другого не указано.
 */
class CatalogTecdocLookupByOemCommand extends Command
{
    protected $signature = 'catalog:tecdoc-lookup-by-oem
        {oems?* : Список OEM-номеров (альтернатива --sku / --suspects)}
        {--sku=* : SKU товаров из БД (основной OEM будет извлечён из SKU до «/»)}
        {--suspects : Брать только товары, помеченные детектором конфликтов}
        {--limit=50 : Максимум товаров/OEM для обработки}
        {--sleep-ms=150 : Пауза между запросами к API (мс)}
        {--format=tsv : Формат отчёта: tsv | csv (csv удобно открывать в Excel)}
        {--tsv= : Путь к отчёту (расширение подставится автоматически по --format)}
        ';

    protected $description = 'По OEM из БД сверяет TecDoc-категорию (RapidAPI tecdoc-catalog) с нашей; пишет TSV-отчёт';

    public function handle(TecDocCatalogService $tecdoc): int
    {
        if (! $tecdoc->isConfigured()) {
            $this->error('RAPIDAPI_TECDOC_CATALOG_KEY/HOST/BASE_URL не настроены. Проверьте .env и config/services.php.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $items = $this->collectLookupItems($limit);
        if ($items->isEmpty()) {
            $this->warn('Не нашлось товаров/OEM для проверки.');

            return self::SUCCESS;
        }

        $format = mb_strtolower(trim((string) $this->option('format')));
        if (! in_array($format, ['tsv', 'csv'], true)) {
            $format = 'tsv';
        }
        $reportPath = (string) $this->option('tsv');
        if (trim($reportPath) === '') {
            $reportPath = storage_path('app/moderation/tecdoc-category-lookup-'.now()->format('Ymd-His').'.'.$format);
        }
        $this->ensureReportHeader($reportPath, $format);

        $this->info('Проверяем '.$items->count().' позиций, сохраняем отчёт в '.$reportPath);

        $rows = [];
        $mismatches = 0;
        $notFound = 0;
        foreach ($items as $item) {
            $oem = (string) $item['oem'];
            if ($oem === '') {
                continue;
            }

            $lookup = $tecdoc->lookupCategoryForOem($oem);

            $tecdocPath = trim(trim((string) $lookup['category_main']).' / '.trim((string) $lookup['category_sub']), ' /');
            $currentPath = (string) $item['current_category_path'];
            $matchStatus = $this->compareCategoryPaths($currentPath, $tecdocPath, (bool) $lookup['found']);
            if ($matchStatus === 'mismatch') {
                $mismatches++;
            }
            if (! $lookup['found']) {
                $notFound++;
            }

            $this->appendReportRow($reportPath, $format, [
                now()->toIso8601String(),
                (string) ($item['product_id'] ?? ''),
                (string) ($item['sku'] ?? ''),
                (string) ($item['product_name'] ?? ''),
                $oem,
                $currentPath,
                $tecdocPath,
                (string) $lookup['article_name'],
                (string) $lookup['supplier_name'],
                (string) ($lookup['article_id'] ?? ''),
                (string) ($lookup['manufacturer_id'] ?? ''),
                $matchStatus,
            ]);

            if (count($rows) < 30) {
                $rows[] = [
                    (string) ($item['product_id'] ?? ''),
                    Str::limit((string) ($item['sku'] ?? ''), 20),
                    Str::limit((string) ($item['product_name'] ?? ''), 40),
                    Str::limit($oem, 24),
                    Str::limit($currentPath, 32),
                    Str::limit($tecdocPath, 32),
                    $matchStatus,
                ];
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        if ($rows !== []) {
            $this->table(
                ['id', 'sku', 'name', 'oem', 'current_cat', 'tecdoc_cat', 'status'],
                $rows,
            );
        }

        $this->info('Итого: '.$items->count().'; не найдено в TecDoc: '.$notFound.'; расхождений категории: '.$mismatches);
        $this->comment('Полный отчёт: '.$reportPath);

        return self::SUCCESS;
    }

    /**
     * Собирает список позиций (OEM + контекст товара) по опциям запуска.
     *
     * @return Collection<int, array{product_id:int|null, sku:string, product_name:string, oem:string, current_category_path:string}>
     */
    protected function collectLookupItems(int $limit): Collection
    {
        $explicitOems = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            (array) $this->argument('oems'),
        )));
        if ($explicitOems !== []) {
            return collect($explicitOems)
                ->take($limit)
                ->map(static fn (string $oem): array => [
                    'product_id' => null,
                    'sku' => '',
                    'product_name' => '',
                    'oem' => $oem,
                    'current_category_path' => '',
                ])
                ->values();
        }

        $skuList = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            (array) $this->option('sku'),
        )));

        $query = Product::query()->with(['category.parent'])->active();
        if ($skuList !== []) {
            $query->whereIn('sku', $skuList);
        } elseif ($this->option('suspects')) {
            $query->whereNotNull('category_id');
        } else {
            $query->whereNotNull('category_id');
        }

        $suspectsOnly = (bool) $this->option('suspects');

        $items = collect();
        $query->orderBy('id')->chunkById(200, function ($chunk) use (&$items, $limit, $suspectsOnly): bool {
            foreach ($chunk as $product) {
                if ($items->count() >= $limit) {
                    return false;
                }
                $path = $this->productCategoryPath($product);
                if ($suspectsOnly) {
                    $reason = CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory(
                        (string) $product->name,
                        $path,
                    );
                    if ($reason === null) {
                        continue;
                    }
                }
                $oem = $this->primaryOemFromSku((string) $product->sku);
                if ($oem === '') {
                    continue;
                }
                $items->push([
                    'product_id' => (int) $product->id,
                    'sku' => (string) $product->sku,
                    'product_name' => (string) $product->name,
                    'oem' => $oem,
                    'current_category_path' => $path,
                ]);
            }

            return true;
        });

        return $items;
    }

    protected function productCategoryPath(Product $product): string
    {
        $leaf = $product->category;
        if ($leaf === null) {
            return '';
        }
        $parent = $leaf->parent;

        return $parent !== null ? $parent->name.' / '.$leaf->name : (string) $leaf->name;
    }

    protected function primaryOemFromSku(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        return trim(Str::limit(explode('/', $sku, 2)[0], 100, ''));
    }

    protected function compareCategoryPaths(string $current, string $tecdoc, bool $apiFound): string
    {
        if (! $apiFound) {
            return 'not_found';
        }
        if (trim($tecdoc) === '') {
            return 'api_no_category';
        }
        if (trim($current) === '') {
            return 'no_current';
        }

        return $this->normalizeForCompare($current) === $this->normalizeForCompare($tecdoc)
            ? 'match'
            : 'mismatch';
    }

    protected function normalizeForCompare(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }

    /**
     * @param 'tsv'|'csv' $format
     */
    protected function ensureReportHeader(string $path, string $format): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        if (is_file($path) && filesize($path) > 0) {
            return;
        }

        $header = [
            'ts', 'product_id', 'sku', 'product_name', 'oem', 'current_category_path',
            'tecdoc_category_path', 'tecdoc_article_name', 'tecdoc_supplier',
            'tecdoc_article_id', 'tecdoc_manufacturer_id', 'status',
        ];

        if ($format === 'csv') {
            // BOM для корректного открытия кириллицы в Excel под Windows.
            File::put($path, "\xEF\xBB\xBF".$this->csvRow($header));

            return;
        }

        File::put($path, $this->tsvRow($header));
    }

    /**
     * @param  list<string>  $row
     * @param  'tsv'|'csv'  $format
     */
    protected function appendReportRow(string $path, string $format, array $row): void
    {
        File::append($path, $format === 'csv' ? $this->csvRow($row) : $this->tsvRow($row));
    }

    /**
     * @param  list<string>  $row
     */
    protected function tsvRow(array $row): string
    {
        $clean = array_map(function (string $s): string {
            $s = str_replace(["\r\n", "\r", "\n"], [' ', ' ', ' '], $s);

            return str_replace("\t", ' ', $s);
        }, $row);

        return implode("\t", $clean)."\n";
    }

    /**
     * CSV с разделителем «;» и экранированием кавычек — удобно для Excel RU.
     *
     * @param  list<string>  $row
     */
    protected function csvRow(array $row): string
    {
        $cells = array_map(function (string $s): string {
            $s = str_replace(["\r\n", "\r", "\n"], [' ', ' ', ' '], $s);

            return '"'.str_replace('"', '""', $s).'"';
        }, $row);

        return implode(';', $cells)."\r\n";
    }
}
