<?php

namespace App\Services;

/**
 * Обход CSV «Остатки»: поиск полного OEM-бандла {@see AutoPartsCatalogService::lookupFullOemBundleForPersistence}
 * для загрузки в БД; строки без совпадения в каталоге — в отдельный *-errors.csv.
 */
class RemainsOemBundleExportService
{
    public function __construct(
        protected AutoPartsCatalogService $catalog,
        protected RemainsStockCsvSectionContextParser $sectionContextParser,
    ) {}

    /**
     * @param  'utf-8'|'cp1251'|null  $csvEncoding
     * @return array{
     *   rows_total: int,
     *   rows_exported: int,
     *   rows_skipped_section: int,
     *   rows_skipped_empty: int,
     *   rows_errors_csv: int,
     *   rows_api_exception: int,
     *   bundles_path: string,
     *   errors_path: string,
     * }
     */
    public function exportJsonl(
        string $inputAbsolutePath,
        string $bundlesAbsolutePath,
        string $errorsAbsolutePath,
        int $sleepMsBetweenRequests,
        int $limit,
        ?string $csvEncoding = null
    ): array {
        if (! $this->catalog->isConfigured()) {
            throw new \RuntimeException('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env.');
        }

        $stats = [
            'rows_total' => 0,
            'rows_exported' => 0,
            'rows_skipped_section' => 0,
            'rows_skipped_empty' => 0,
            'rows_errors_csv' => 0,
            'rows_api_exception' => 0,
            'bundles_path' => $bundlesAbsolutePath,
            'errors_path' => $errorsAbsolutePath,
        ];

        $header = RemainsStockCsvReader::readHeaderRow($inputAbsolutePath, $csvEncoding);
        $out = fopen($bundlesAbsolutePath, 'wb');
        if ($out === false) {
            throw new \RuntimeException("Не удалось создать файл: {$bundlesAbsolutePath}");
        }
        $err = fopen($errorsAbsolutePath, 'wb');
        if ($err === false) {
            fclose($out);
            throw new \RuntimeException("Не удалось создать файл: {$errorsAbsolutePath}");
        }

        fwrite($err, "\xEF\xBB\xBF");
        $errorHeader = array_merge($header, ['error', 'oem_candidates_tried']);
        fputcsv($err, $errorHeader, ',');

        $importContext = [
            'standalone' => true,
            'part_brand_id' => null,
            'vehicles' => [],
        ];

        try {
            foreach (RemainsStockCsvReader::iterateDataRows($inputAbsolutePath, $csvEncoding) as $row) {
                $stats['rows_total']++;
                $row = array_pad($row, 16, '');
                $code = trim((string) ($row[1] ?? ''));
                $skuRaw = trim((string) ($row[2] ?? ''));
                $name = trim((string) ($row[3] ?? ''));

                if (RemainsStockCsvReader::isSectionHeaderRow($skuRaw, $name, $code)) {
                    $importContext = $this->sectionContextParser->parse($code, false);
                    $stats['rows_skipped_section']++;

                    continue;
                }

                if ($skuRaw === '' || $name === '') {
                    $stats['rows_skipped_empty']++;

                    continue;
                }

                if ($limit > 0 && $stats['rows_exported'] >= $limit) {
                    break;
                }

                $codeAlt = $code !== '' ? $code : null;
                $candidateRows = $this->catalog->partNumberSearchCandidatesDetailed($skuRaw, $codeAlt);
                $candidates = array_map(
                    static fn (array $row): string => (string) ($row['candidate'] ?? ''),
                    $candidateRows
                );
                $tried = implode(' | ', array_filter($candidates, static fn (string $v): bool => $v !== ''));

                $bundle = null;
                $usedOem = null;
                $apiError = null;
                foreach ($candidateRows as $candidateRow) {
                    if (! ($candidateRow['allowed'] ?? true)) {
                        continue;
                    }
                    $cand = (string) ($candidateRow['candidate'] ?? '');
                    if ($cand === '') {
                        continue;
                    }
                    try {
                        $b = $this->catalog->lookupFullOemBundleForPersistence($cand);
                        if ($this->bundleHasOemCatalogHit($b)) {
                            $bundle = $b;
                            $usedOem = $cand;
                            break;
                        }
                    } catch (\Throwable $e) {
                        $apiError = $e->getMessage();
                    }
                }

                if ($bundle === null) {
                    $msg = $apiError !== null
                        ? 'Ошибка API каталога: '.$apiError
                        : 'В каталоге не найдено по OEM (перебраны кандидаты номера).';
                    fputcsv($err, array_merge($row, [$msg, $tried]), ',');
                    $stats['rows_errors_csv']++;
                    if ($apiError !== null) {
                        $stats['rows_api_exception']++;
                    }
                } else {
                    $csvAssoc = $this->rowToAssoc($header, $row);
                    $csvRow = array_slice(array_pad($row, 16, ''), 0, 16);
                    $line = json_encode(
                        [
                            'schema_version' => 2,
                            'oem_queried_from_csv' => $skuRaw,
                            'oem_used_for_catalog' => $usedOem,
                            'oem_candidates_tried' => $candidates,
                            'csv' => $csvAssoc,
                            'csv_row' => $csvRow,
                            'import_context' => $importContext,
                            'catalog' => $bundle,
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    );
                    fwrite($out, $line."\n");
                    $stats['rows_exported']++;
                    if ($sleepMsBetweenRequests > 0) {
                        usleep(max(0, $sleepMsBetweenRequests) * 1000);
                    }
                }
            }
        } finally {
            fclose($out);
            fclose($err);
        }

        return $stats;
    }

    /**
     * @param  list<string>  $header
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    protected function rowToAssoc(array $header, array $row): array
    {
        $out = [];
        foreach ($header as $i => $key) {
            $k = trim((string) $key);
            if ($k === '') {
                $k = 'column_'.$i;
            }
            $out[$k] = (string) ($row[$i] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    protected function bundleHasOemCatalogHit(array $bundle): bool
    {
        $rows = $bundle['source_payload']['oem_search_rows'] ?? null;

        return is_array($rows) && $rows !== [];
    }
}
