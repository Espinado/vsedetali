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

    private string $csvDelimiter = ',';

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

        [$handle, $this->csvDelimiter, $normalizedContent] = $this->openNormalizedCsvStream($absolutePath);

        $this->seekStreamAfterHeaderRow($handle, $normalizedContent, $this->csvDelimiter);

        try {
            while (($row = $this->fgetcsvRow($handle)) !== false) {
                $stats['rows']++;

                $row = array_map(fn ($c) => $this->normalizeUtf8String(trim((string) ($c ?? ''), " \t\n\r\0\x0B\xEF\xBB\xBF")), $row);
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
        } finally {
            fclose($handle);
        }

        return $stats;
    }

    /**
     * Читает строку CSV с разделителем по умолчанию для текущей версии PHP (escape в PHP 8.4+ = «», в 8.3− = «\»).
     *
     * @return array<int, string|null>|false
     */
    private function fgetcsvRow($handle): array|false
    {
        // Без 5-го аргумента: в PHP 8.4+ по умолчанию escape = «», в 8.3− = «\» — так корректно разбираются кавычки.
        return fgetcsv($handle, 0, $this->csvDelimiter, '"');
    }

    /**
     * Позиционирует поток на байт сразу после строки заголовка (по подстроке «,Код,Артикул,»).
     *
     * Проверку заголовка делаем по маркеру и regex (не по str_getcsv с фиксированным escape): в PHP 8.4+
     * разбор кавычек отличается от 8.3, плюс в файле часто лишняя запятая в начале строки.
     */
    private function seekStreamAfterHeaderRow($handle, string $content, string $delimiter): void
    {
        $markers = [',Код,Артикул,', ';Код;Артикул;'];
        $pos = false;
        $matchedMarker = null;
        foreach ($markers as $m) {
            $pos = strpos($content, $m);
            if ($pos !== false) {
                $matchedMarker = $m;

                break;
            }
        }

        if ($pos === false || $matchedMarker === null) {
            throw new \RuntimeException(
                'В тексте файла не найдена подстрока «,Код,Артикул,» (или с «;»). Сохраните CSV в UTF-8 и одну строку заголовка.'
            );
        }

        $lineStart = strrpos(substr($content, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $lineEnd = strpos($content, "\n", $lineStart);
        $headerLine = $lineEnd === false
            ? substr($content, $lineStart)
            : substr($content, $lineStart, $lineEnd - $lineStart);

        if (! str_contains($headerLine, $matchedMarker)) {
            throw new \RuntimeException(
                'Строка заголовка обрезана неверно. Начало: '.Str::limit($headerLine, 240)
            );
        }

        // Дополнительно: в этой строке «Код» и «Артикул» идут подряд как колонки (на случай ложного совпадения в тексте).
        if (! $this->headerLineLooksLikeDataTable($headerLine, $delimiter)) {
            throw new \RuntimeException(
                'Строка с «Код»/«Артикул» не похожа на заголовок таблицы. Начало строки: '.Str::limit($headerLine, 240)
            );
        }

        $nextOffset = $lineEnd === false ? strlen($content) : $lineEnd + 1;
        rewind($handle);
        fseek($handle, $nextOffset);
    }

    /**
     * Проверка без str_getcsv: в 1С/Excel в начале строки часто лишняя «,», а кавычки в заголовке
     * по-разному разбираются с escape в разных версиях PHP.
     */
    private function headerLineLooksLikeDataTable(string $headerLine, string $delimiter): bool
    {
        if ($delimiter === ',') {
            return (bool) preg_match('/(?:^|,)\s*Код\s*,\s*Артикул\s*(?:,|$)/u', $headerLine);
        }

        return (bool) preg_match('/(?:^|;)\s*Код\s*;\s*Артикул\s*(?:;|$)/u', $headerLine);
    }

    /**
     * Читает файл в память и нормализует типичные проблемы Excel/1С:
     * - UTF-8 BOM;
     * - «умные» кавычки вместо ASCII " (иначе str_getcsv ломает колонки);
     * - перенос строки внутри заголовка в поле «Сумма / себестоимости»;
     * - разделитель «,» или «;».
     *
     * @return array{0: resource, 1: string, 2: string}
     */
    private function openNormalizedCsvStream(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw new \RuntimeException('Не удалось прочитать файл.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Единый перевод строки — иначе склейка ломается
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Не конвертируем «как CP1251» в UTF-8: при уже корректном UTF-8 это портит «Код»/«Артикул».

        // Типографские кавычки → ASCII " (иначе fgetcsv не распознаёт поля)
        $content = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xC2\xAB", "\xC2\xBB"],
            '"',
            $content
        );

        // Заголовок: внутри кавычек «Сумма» + любые пробелы/переносы + «себестоимости» → одна строка
        $content = preg_replace('/"Сумма\s+себестоимости"/u', '"Сумма себестоимости"', $content);
        $content = str_replace('"Сумма'."\n".'себестоимости"', '"Сумма себестоимости"', $content);

        if (! str_contains($content, ',Код,Артикул,') && ! str_contains($content, ';Код;Артикул;')) {
            throw new \RuntimeException(
                'После разбора CSV не найдена строка заголовка с «Код» и «Артикул». '.
                'Уберите перенос строки внутри ячейки «Сумма / себестоимости» в первой строке таблицы или сохраните файл как CSV UTF-8.'
            );
        }

        $delimiter = ',';
        if (str_contains($content, ',Код,Артикул,') || preg_match('/(^|\n),Код,Артикул,/u', $content)) {
            $delimiter = ',';
        } elseif (str_contains($content, ';Код;Артикул;') || preg_match('/(^|\n);Код;Артикул;/u', $content)) {
            $delimiter = ';';
        } else {
            foreach (explode("\n", $content) as $line) {
                if (str_contains($line, 'Код') && str_contains($line, 'Артикул')) {
                    $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';

                    break;
                }
            }
        }

        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось создать временный поток.');
        }
        fwrite($handle, $content);
        rewind($handle);

        return [$handle, $delimiter, $content];
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

    private function normalizeUtf8String(string $s): string
    {
        if ($s === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($clean !== false) {
                $s = $clean;
            }
        }

        if (! mb_check_encoding($s, 'UTF-8')) {
            $converted = @mb_convert_encoding($s, 'UTF-8', 'Windows-1251');
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $s;
    }
}
