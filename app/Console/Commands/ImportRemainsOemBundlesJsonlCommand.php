<?php

namespace App\Console\Commands;

use App\Services\RemainsStockCsvImportService;
use Illuminate\Console\Command;

/**
 * Читает JSONL от {@see ExportRemainsCsvOemBundlesCommand} и пишет в БД без вызовов RapidAPI
 * (параллельно с повторной выгрузкой можно гнать второй процесс на уже записанные строки файла).
 */
class ImportRemainsOemBundlesJsonlCommand extends Command
{
    protected $signature = 'catalog:import-oem-bundles-jsonl
        {path : Путь к *-oem-bundles.jsonl}
        {--force : Не спрашивать подтверждение}
        {--dry-run : Только проверка строк, без записи в БД}
        {--no-skip-existing : Пытаться вставить даже при существующем SKU (обычно приведёт к пропуску в транзакции)}
        {--no-catalog-images : Не скачивать фото в storage}
        {--from-line=1 : Номер строки JSONL (1-based), с которой начать}
        {--limit=0 : Максимум обработанных строк (0 = без лимита)}';

    protected $description = 'Импорт товаров из JSONL OEM-бандлов (consumer: второй процесс параллельно с catalog:export-oem-bundles — см. --from-line после частичной выгрузки)';

    public function handle(RemainsStockCsvImportService $importService): int
    {
        $path = trim((string) $this->argument('path'));
        if ($path === '' || ! is_readable($path)) {
            $this->error('Укажите существующий файл JSONL.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = ! (bool) $this->option('no-skip-existing');
        $downloadImages = (bool) $this->option('no-catalog-images') ? false : null;

        $fromLine = max(1, (int) $this->option('from-line'));
        $limit = max(0, (int) $this->option('limit'));

        if (! $this->option('force') && ! $dryRun && ! $this->confirm(
            'Импортировать товары из JSONL в базу? Существующие SKU будут пропущены (если не указан --no-skip-existing). Продолжить?'
        )) {
            $this->warn('Отменено.');

            return self::FAILURE;
        }

        $totals = [
            'lines_read' => 0,
            'lines_ok' => 0,
            'lines_skipped_existing' => 0,
            'lines_errors' => 0,
            'created_products' => 0,
            'catalog_images_attached' => 0,
        ];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Не удалось открыть файл.');

            return self::FAILURE;
        }

        try {
            $lineNo = 0;
            $processed = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNo++;
                if ($lineNo < $fromLine) {
                    continue;
                }

                $trim = trim($line);
                if ($trim === '') {
                    continue;
                }

                $totals['lines_read']++;

                try {
                    $payload = json_decode($trim, true, 2048, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {
                    $totals['lines_errors']++;
                    $this->warn("Строка {$lineNo}: JSON — ".$e->getMessage());
                    if ($limit > 0 && ++$processed >= $limit) {
                        break;
                    }

                    continue;
                }

                if (! is_array($payload)) {
                    $totals['lines_errors']++;
                    if ($limit > 0 && ++$processed >= $limit) {
                        break;
                    }

                    continue;
                }

                $result = $importService->importFromOemBundleJsonlPayload(
                    $payload,
                    $dryRun,
                    $skipExisting,
                    $downloadImages
                );

                if (! ($result['ok'] ?? false)) {
                    $totals['lines_errors']++;
                    $err = (string) ($result['error'] ?? 'unknown');
                    $this->warn("Строка {$lineNo}: {$err}");

                    if ($limit > 0 && ++$processed >= $limit) {
                        break;
                    }

                    continue;
                }

                if (($result['skipped'] ?? null) === 'existing') {
                    $totals['lines_skipped_existing']++;
                } else {
                    $totals['lines_ok']++;
                }

                $patch = $result['stats_patch'] ?? [];
                $totals['created_products'] += (int) ($patch['created_products'] ?? 0);
                $totals['catalog_images_attached'] += (int) ($patch['catalog_images_attached'] ?? 0);

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
                ['Строк JSON прочитано', $totals['lines_read']],
                ['Успешно (новые или без ошибки)', $totals['lines_ok']],
                ['Пропущено (уже есть SKU)', $totals['lines_skipped_existing']],
                ['Ошибок строк', $totals['lines_errors']],
                ['Создано товаров', $totals['created_products']],
                ['Фото каталога прикреплено', $totals['catalog_images_attached']],
            ]
        );

        return self::SUCCESS;
    }
}
