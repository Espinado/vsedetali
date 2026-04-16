<?php

namespace App\Console\Commands;

use App\Services\RemainsStockCsvImportService;
use App\Support\ProductNameVehicleExtractor;
use Illuminate\Console\Command;

/**
 * Проверка JSONL перед/после импорта: JSON, OEM-бандл, SKU/название, дубликаты SKU в файле,
 * предупреждение «ведущая марка в названии ≠ import_context.vehicles».
 */
class CatalogVerifyOemBundlesJsonlCommand extends Command
{
    protected $signature = 'catalog:verify-oem-bundles-jsonl
        {path : Путь к *-oem-bundles.jsonl}
        {--limit=0 : Максимум непустых строк (0 = все)}
        {--show-conflicts=15 : Сколько строк с конфликтом марки в названии показать (0 = не показывать)}
        {--show-errors=15 : Сколько строк с ошибкой показать (0 = не показывать)}';

    protected $description = 'Проверка JSONL OEM-бандлов без записи в БД';

    public function handle(RemainsStockCsvImportService $importService): int
    {
        ProductNameVehicleExtractor::clearMakesCache();

        $path = trim((string) $this->argument('path'));
        if ($path === '' || ! is_readable($path)) {
            $this->error('Файл не найден или недоступен для чтения.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $showConflicts = max(0, (int) $this->option('show-conflicts'));
        $showErrors = max(0, (int) $this->option('show-errors'));

        $totals = [
            'lines_nonempty' => 0,
            'json_errors' => 0,
            'ok' => 0,
            'errors' => 0,
            'dup_sku_in_file' => 0,
            'name_context_conflicts' => 0,
            'compat_rows_gt_0' => 0,
        ];

        $skuFirstLine = [];
        $conflictSamples = [];
        $errorSamples = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Не удалось открыть файл.');

            return self::FAILURE;
        }

        $lineNo = 0;
        $processed = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                $lineNo++;
                $trim = trim($line);
                if ($trim === '') {
                    continue;
                }

                $totals['lines_nonempty']++;

                try {
                    $payload = json_decode($trim, true, 2048, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $totals['json_errors']++;
                    if (count($errorSamples) < $showErrors) {
                        $errorSamples[] = ['line' => $lineNo, 'msg' => 'json: '.$e->getMessage()];
                    }

                    if ($limit > 0 && ++$processed >= $limit) {
                        break;
                    }

                    continue;
                }

                if (! is_array($payload)) {
                    $totals['json_errors']++;
                    if (count($errorSamples) < $showErrors) {
                        $errorSamples[] = ['line' => $lineNo, 'msg' => 'payload не массив'];
                    }
                    if ($limit > 0 && ++$processed >= $limit) {
                        break;
                    }

                    continue;
                }

                $analysis = $importService->analyzeOemBundleJsonlPayload($payload);

                if (! ($analysis['ok'] ?? false)) {
                    $totals['errors']++;
                    if (count($errorSamples) < $showErrors) {
                        $err = (string) ($analysis['error'] ?? 'unknown');
                        $sku = (string) ($analysis['sku'] ?? '');
                        $errorSamples[] = ['line' => $lineNo, 'msg' => $err.($sku !== '' ? " (sku: {$sku})" : '')];
                    }
                } else {
                    $totals['ok']++;
                    $sku = (string) ($analysis['sku'] ?? '');
                    if ($sku !== '') {
                        if (isset($skuFirstLine[$sku])) {
                            $totals['dup_sku_in_file']++;
                            if (count($errorSamples) < $showErrors) {
                                $errorSamples[] = [
                                    'line' => $lineNo,
                                    'msg' => "дубликат SKU в файле (первая строка {$skuFirstLine[$sku]})",
                                ];
                            }
                        } else {
                            $skuFirstLine[$sku] = $lineNo;
                        }
                    }
                    if (($analysis['catalog_compat_vehicle_rows'] ?? 0) > 0) {
                        $totals['compat_rows_gt_0']++;
                    }
                    if (! empty($analysis['name_lead_make_conflicts_context'])) {
                        $totals['name_context_conflicts']++;
                        if (count($conflictSamples) < $showConflicts) {
                            $conflictSamples[] = [
                                'line' => $lineNo,
                                'sku' => $sku,
                                'ctx' => (string) ($analysis['import_context_vehicle_make'] ?? ''),
                                'name' => mb_substr((string) ($analysis['name'] ?? ''), 0, 120),
                            ];
                        }
                    }
                }

                if ($limit > 0 && ++$processed >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Непустых строк JSONL', $totals['lines_nonempty']],
                ['Ошибок JSON', $totals['json_errors']],
                ['Строк с валидной структурой (ok)', $totals['ok']],
                ['Строк с ошибкой разбора (catalog/csv/oem)', $totals['errors']],
                ['Дубликатов SKU внутри файла', $totals['dup_sku_in_file']],
                ['Конфликт: марка в начале названия ≠ import_context', $totals['name_context_conflicts']],
                ['Строк с compatibility.vehicles (make+model) в catalog', $totals['compat_rows_gt_0']],
            ]
        );

        if ($conflictSamples !== []) {
            $this->newLine();
            $this->warn('Примеры конфликта названия с import_context (марка в названии по каталогу ≠ контексту):');
            $this->table(['Строка', 'SKU', 'Контекст make', 'Начало name'], array_map(static fn (array $r): array => [
                (string) $r['line'],
                $r['sku'],
                $r['ctx'],
                $r['name'],
            ], $conflictSamples));
        }

        if ($errorSamples !== []) {
            $this->newLine();
            $this->warn('Примеры ошибок / дубликатов SKU:');
            $this->table(['Строка', 'Сообщение'], array_map(static fn (array $r): array => [
                (string) $r['line'],
                $r['msg'],
            ], $errorSamples));
        }

        $this->newLine();
        $this->comment('Сброс только товаров и связей: php artisan catalog:reset-for-import --force');
        $this->comment('Импорт: php artisan catalog:import-oem-bundles-jsonl "'.str_replace('"', '\"', $path).'" --force');

        return self::SUCCESS;
    }
}
