<?php

namespace App\Services;

/**
 * Обогащение CSV «Остатки» данными каталога (категория, производители, аналоги, применимость) и проверка полноты полей.
 */
class StockCsvEnrichmentService
{
    public const SOURCE_HEADER = 'Источник каталога';

    public const SKU_HEADER = 'Артикул';

    public function __construct(
        protected AutoPartsCatalogService $catalog
    ) {}

    /**
     * @param  array{
     *   resume_from_csv?: string|null,
     *   only_skus_file?: string|null,
     *   write_not_found_list?: bool,
     *   not_found_output_path?: string|null,
     *   csv_encoding?: 'utf-8'|'cp1251'|null,
     * }  $options
     * @return array{
     *   rows_total: int,
     *   rows_enriched: int,
     *   rows_resumed: int,
     *   rows_passthrough_only_sku_filter: int,
     *   rows_skipped_section: int,
     *   rows_skipped_empty: int,
     *   rows_skipped_not_found: int,
     *   errors: int,
     *   not_found_list_path: string|null,
     * }
     */
    public function enrichToFile(
        string $inputAbsolutePath,
        string $outputAbsolutePath,
        int $limit = 0,
        int $sleepMsBetweenRequests = 150,
        array $options = []
    ): array {
        if (! $this->catalog->isConfigured()) {
            throw new \RuntimeException(
                'Задайте RAPIDAPI_AUTO_PARTS_KEY в .env для обращения к каталогу.'
            );
        }

        $resumePath = isset($options['resume_from_csv']) ? trim((string) $options['resume_from_csv']) : '';
        $resumePath = $resumePath !== '' ? $resumePath : null;
        $onlySkusPath = isset($options['only_skus_file']) ? trim((string) $options['only_skus_file']) : '';
        $onlySkusPath = $onlySkusPath !== '' ? $onlySkusPath : null;
        $writeNotFound = array_key_exists('write_not_found_list', $options)
            ? (bool) $options['write_not_found_list']
            : true;
        $notFoundCustom = isset($options['not_found_output_path']) ? trim((string) $options['not_found_output_path']) : '';
        $notFoundCustom = $notFoundCustom !== '' ? $notFoundCustom : null;

        $csvEncoding = isset($options['csv_encoding']) ? $options['csv_encoding'] : null;
        if ($csvEncoding !== null && $csvEncoding !== 'utf-8' && $csvEncoding !== 'cp1251') {
            $csvEncoding = null;
        }

        $resumeMap = $resumePath !== null && is_file($resumePath)
            ? $this->loadResumeRowsBySku($resumePath)
            : [];

        $onlySkuSet = $onlySkusPath !== null && is_file($onlySkusPath)
            ? $this->loadSkuSetFromTextFile($onlySkusPath)
            : null;

        $notFoundPath = null;
        $notFoundHandle = null;
        if ($writeNotFound) {
            $notFoundPath = $notFoundCustom ?? $this->defaultNotFoundListPath($outputAbsolutePath);
            $notFoundHandle = fopen($notFoundPath, 'wb');
            if ($notFoundHandle === false) {
                throw new \RuntimeException("Не удалось записать список не найденных: {$notFoundPath}");
            }
        }

        $stats = [
            'rows_total' => 0,
            'rows_enriched' => 0,
            'rows_resumed' => 0,
            'rows_passthrough_only_sku_filter' => 0,
            'rows_skipped_section' => 0,
            'rows_skipped_empty' => 0,
            'rows_skipped_not_found' => 0,
            'errors' => 0,
            'not_found_list_path' => $notFoundPath,
        ];

        $out = fopen($outputAbsolutePath, 'wb');
        if ($out === false) {
            if ($notFoundHandle !== null) {
                fclose($notFoundHandle);
            }
            throw new \RuntimeException("Не удалось записать файл: {$outputAbsolutePath}");
        }

        fwrite($out, "\xEF\xBB\xBF");

        $headerBase = RemainsStockCsvReader::readHeaderRow($inputAbsolutePath, $csvEncoding);

        $extraHeaders = [
            'Категория (каталог)',
            'Подкатегория (каталог)',
            'Производители (OEM-поиск)',
            'Аналоги aftermarket (кросс)',
            'Совместимость (сводка)',
            'Марка (пример)',
            'Модель (пример)',
            'Тип кузова (пример)',
            'Годы выпуска (пример)',
            'Объём двигателя (пример)',
            'Полнота полей',
            'Не хватает полей',
            'Источник каталога',
        ];

        $idxCompleteness = 10;
        $idxMissing = 11;

        fputcsv($out, array_merge($headerBase, $extraHeaders), ',');

        try {
            foreach (RemainsStockCsvReader::iterateDataRows($inputAbsolutePath, $csvEncoding) as $row) {
                $stats['rows_total']++;

                $row = array_pad($row, 16, '');
                $code = $row[1] ?? '';
                $skuRaw = $row[2] ?? '';
                $name = $row[3] ?? '';

                if ($this->isSectionHeaderRow($skuRaw, $name, $code)) {
                    fputcsv($out, array_merge($row, array_fill(0, count($extraHeaders), '')), ',');
                    $stats['rows_skipped_section']++;

                    continue;
                }

                if ($skuRaw === '' || $name === '') {
                    fputcsv($out, array_merge($row, array_fill(0, count($extraHeaders), '')), ',');
                    $stats['rows_skipped_empty']++;

                    continue;
                }

                if ($onlySkuSet !== null && ! isset($onlySkuSet[$skuRaw])) {
                    if (isset($resumeMap[$skuRaw])) {
                        fputcsv($out, $resumeMap[$skuRaw], ',');
                        $stats['rows_resumed']++;
                    } else {
                        fputcsv($out, array_merge($row, array_fill(0, count($extraHeaders), '')), ',');
                        $stats['rows_passthrough_only_sku_filter']++;
                    }

                    continue;
                }

                if (isset($resumeMap[$skuRaw])) {
                    fputcsv($out, $resumeMap[$skuRaw], ',');
                    $stats['rows_resumed']++;

                    continue;
                }

                if ($limit > 0 && $stats['rows_enriched'] >= $limit) {
                    break;
                }

                try {
                    $codeAlt = trim($code) !== '' ? trim($code) : null;
                    $enriched = $this->catalog->lookupEnrichedForStockWithCandidates($skuRaw, $codeAlt, $name);
                    if (($enriched['source'] ?? '') === 'none') {
                        $stats['rows_skipped_not_found']++;
                        if ($notFoundHandle !== null) {
                            fwrite($notFoundHandle, $skuRaw.($code !== '' ? "\t".trim($code) : '')."\n");
                        }

                        continue;
                    }

                    $validation = $this->validateEnrichment($enriched);
                    $summary = $this->buildApplicabilitySummary($enriched['vehicles_normalized']);
                    $first = $enriched['vehicles_normalized'][0] ?? null;
                    $years = $this->formatYears($first);

                    $append = [
                        $enriched['category_main'],
                        $enriched['category_sub'],
                        $this->formatOemSuppliersLine($enriched['oem_suppliers'] ?? []),
                        $this->formatCrossAnalogsLine($enriched['cross_analogs'] ?? []),
                        $summary,
                        $first['make'] ?? '',
                        $first['model'] ?? '',
                        $first['body_type'] ?? '',
                        $years,
                        $first['engine'] ?? '',
                        $validation['status'],
                        implode('; ', $validation['missing']),
                        $enriched['source'],
                    ];

                    fputcsv($out, array_merge($row, $append), ',');
                    $stats['rows_enriched']++;

                    if ($sleepMsBetweenRequests > 0) {
                        usleep($sleepMsBetweenRequests * 1000);
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $append = array_fill(0, count($extraHeaders), '');
                    $append[$idxCompleteness] = 'error';
                    $append[$idxMissing] = 'ошибка API: '.$e->getMessage();
                    fputcsv($out, array_merge($row, $append), ',');
                }
            }
        } finally {
            fclose($out);
            if ($notFoundHandle !== null) {
                fclose($notFoundHandle);
            }
        }

        return $stats;
    }

    /**
     * Строки из ранее сохранённого *-enriched.csv: ключ — артикул, значение — полная строка CSV (как в файле).
     *
     * @return array<string, list<string|int|float>>
     */
    protected function loadResumeRowsBySku(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return [];
        }

        $map = [];
        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                return [];
            }
            $header = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\xEF\xBB\xBF"), $header);
            $artIdx = array_search(self::SKU_HEADER, $header, true);
            $srcIdx = array_search(self::SOURCE_HEADER, $header, true);
            if ($artIdx === false || $srcIdx === false) {
                return [];
            }

            while (($row = fgetcsv($handle)) !== false) {
                $sku = trim((string) ($row[$artIdx] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $src = strtolower(trim((string) ($row[$srcIdx] ?? '')));
                if (! in_array($src, ['oem', 'article'], true)) {
                    continue;
                }
                $map[$sku] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $map;
    }

    /**
     * @return array<string, true>
     */
    protected function loadSkuSetFromTextFile(string $absolutePath): array
    {
        $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Не удалось прочитать файл списка SKU: {$absolutePath}");
        }

        $set = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $sku = trim(explode("\t", $line, 2)[0]);
            if ($sku !== '') {
                $set[$sku] = true;
            }
        }

        return $set;
    }

    protected function defaultNotFoundListPath(string $enrichedCsvPath): string
    {
        $base = preg_replace('/\.csv$/iu', '', $enrichedCsvPath);

        return ($base !== null && $base !== '' ? $base : $enrichedCsvPath).'-not-found.txt';
    }

    /**
     * @param  list<array{supplierId: int, supplierName: string, articleNo: string|null, articleId: int|null}>  $suppliers
     */
    public function formatOemSuppliersLine(array $suppliers): string
    {
        $parts = [];
        foreach ($suppliers as $s) {
            $name = trim((string) ($s['supplierName'] ?? ''));
            $art = isset($s['articleNo']) && $s['articleNo'] !== null && $s['articleNo'] !== ''
                ? trim((string) $s['articleNo'])
                : '';
            if ($name === '') {
                continue;
            }
            $parts[] = $art !== '' ? "{$name} ({$art})" : $name;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  list<array{supplierName: string, articleNo: string, crossManufacturerName: string|null, crossNumber: string|null, searchLevel: string}>  $analogs
     */
    public function formatCrossAnalogsLine(array $analogs): string
    {
        $parts = [];
        foreach ($analogs as $a) {
            $brand = trim((string) ($a['supplierName'] ?? ''));
            $num = trim((string) ($a['articleNo'] ?? ''));
            $oemM = isset($a['crossManufacturerName']) ? trim((string) $a['crossManufacturerName']) : '';
            $oemN = isset($a['crossNumber']) ? trim((string) $a['crossNumber']) : '';
            if ($brand === '' && $num === '') {
                continue;
            }
            $left = trim($brand.' '.$num);
            if ($oemM !== '' || $oemN !== '') {
                $left .= ' → OEM '.trim($oemM.' '.$oemN);
            }
            $parts[] = $left;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  list<array{make: string, model: string, body_type: string, year_from: int|null, year_to: int|null, engine: string}>  $vehicles
     */
    public function buildApplicabilitySummary(array $vehicles): string
    {
        if ($vehicles === []) {
            return '';
        }

        $parts = [];
        foreach ($vehicles as $v) {
            $y = $this->formatYears($v);
            $body = $v['body_type'] !== '' ? '('.$v['body_type'].')' : '';
            $chunk = trim(implode(' ', array_filter([
                $v['make'],
                $v['model'],
                $body !== '()' ? $body : '',
                $y !== '' ? $y : '',
                $v['engine'] !== '' ? $v['engine'] : '',
            ], fn ($x) => $x !== '')));
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $enriched  результат {@see AutoPartsCatalogService::lookupEnrichedForStock}
     * @return array{status: string, missing: list<string>}
     */
    public function validateEnrichment(array $enriched): array
    {
        $missing = [];

        if (($enriched['source'] ?? '') === 'none') {
            return ['status' => 'not_found', 'missing' => ['деталь не найдена в каталоге']];
        }

        $main = trim((string) ($enriched['category_main'] ?? ''));
        $sub = trim((string) ($enriched['category_sub'] ?? ''));
        if ($main === '' && $sub === '') {
            $missing[] = 'категория';
        }

        $oemSup = $enriched['oem_suppliers'] ?? [];
        $cross = $enriched['cross_analogs'] ?? [];
        if ((! is_array($oemSup) || $oemSup === []) && (! is_array($cross) || $cross === [])) {
            $missing[] = 'производители/аналоги (нет данных в OEM и кросс-таблице)';
        }

        $vehicles = $enriched['vehicles_normalized'] ?? [];
        if (! is_array($vehicles) || $vehicles === []) {
            if (($enriched['source'] ?? '') === 'article') {
                $missing[] = 'применимость к авто (список машин в API только по OEM-номеру; номер найден как артикул без применимости)';
            } else {
                $missing[] = 'применимость (марка, модель, кузов, годы, объём)';
            }
        }

        foreach ($vehicles as $i => $v) {
            if (! is_array($v)) {
                continue;
            }
            $n = $i + 1;
            if (trim((string) ($v['make'] ?? '')) === '') {
                $missing[] = "марка (авто #{$n})";
            }
            if (trim((string) ($v['model'] ?? '')) === '') {
                $missing[] = "модель (авто #{$n})";
            }
            if (trim((string) ($v['body_type'] ?? '')) === '') {
                $missing[] = "тип кузова (авто #{$n})";
            }
            $yf = $v['year_from'] ?? null;
            $yt = $v['year_to'] ?? null;
            if ($yf === null && $yt === null) {
                $missing[] = "годы выпуска (авто #{$n})";
            }
            if (trim((string) ($v['engine'] ?? '')) === '') {
                $missing[] = "объём двигателя (авто #{$n})";
            }
        }

        $missing = array_values(array_unique($missing));
        $status = $missing === [] ? 'ok' : 'partial';

        return ['status' => $status, 'missing' => $missing];
    }

    /**
     * @param  array{make?: string, model?: string, body_type?: string, year_from?: int|null, year_to?: int|null, engine?: string}|null  $v
     */
    protected function formatYears(?array $v): string
    {
        if ($v === null) {
            return '';
        }
        $yf = $v['year_from'] ?? null;
        $yt = $v['year_to'] ?? null;
        if ($yf !== null && $yt !== null) {
            return $yf === $yt ? (string) $yf : "{$yf}–{$yt}";
        }
        if ($yf !== null) {
            return (string) $yf;
        }
        if ($yt !== null) {
            return (string) $yt;
        }

        return '';
    }

    protected function isSectionHeaderRow(string $skuRaw, string $name, string $code): bool
    {
        return $skuRaw === '' && $name === '' && trim($code) !== '';
    }
}
