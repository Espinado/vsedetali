<?php

namespace App\Console\Commands;

use App\Services\RemainsOemBundleExportService;
use Illuminate\Console\Command;

/**
 * По каждой строке CSV «Остатки» строит полный OEM-бандл для записи в БД (JSON Lines).
 * Строки без попадания в каталог (и при ошибке API) — в CSV с именем *-errors.csv.
 */
class ExportRemainsCsvOemBundlesCommand extends Command
{
    protected $signature = 'catalog:export-oem-bundles
        {path : Путь к CSV «Остатки» (Код, Артикул, …)}
        {--output= : JSONL с бандлами (по умолчанию рядом с файлом: имя-oem-bundles.jsonl)}
        {--errors= : CSV строк с ошибками (по умолчанию: имя-errors.csv)}
        {--encoding= : utf-8|cp1251 — кодировка чтения CSV; не указано = авто}
        {--sleep=200 : Пауза между запросами к каталогу, мс}
        {--limit=0 : Ограничить число успешно выгруженных строк (0 = без лимита)}';

    protected $description = 'Выгрузка полных OEM-бандлов по всем артикулам из CSV «Остатки»; не найденные — в *-errors.csv';

    public function handle(RemainsOemBundleExportService $service): int
    {
        $path = trim((string) $this->argument('path'));
        if ($path === '') {
            $this->error('Укажите путь к CSV.');

            return self::FAILURE;
        }
        if (! is_readable($path)) {
            $this->error("Файл не найден или недоступен: {$path}");

            return self::FAILURE;
        }

        $real = realpath($path);
        $baseDir = $real !== false ? dirname($real) : dirname($path);
        $stem = preg_replace('/\.csv$/iu', '', basename($path));
        if ($stem === null || $stem === '') {
            $stem = 'remains-export';
        }

        $outOpt = trim((string) $this->option('output'));
        $bundlesPath = $outOpt !== '' ? $outOpt : $baseDir.DIRECTORY_SEPARATOR.$stem.'-oem-bundles.jsonl';

        $errOpt = trim((string) $this->option('errors'));
        $errorsPath = $errOpt !== '' ? $errOpt : $baseDir.DIRECTORY_SEPARATOR.$stem.'-errors.csv';

        $encRaw = strtolower(trim((string) $this->option('encoding')));
        $csvEncoding = match ($encRaw) {
            'utf-8', 'utf8' => 'utf-8',
            'cp1251', 'windows-1251' => 'cp1251',
            default => null,
        };

        $sleep = max(0, (int) $this->option('sleep'));
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Вход: '.$path);
        $this->info('JSONL: '.$bundlesPath);
        $this->info('Ошибки: '.$errorsPath);

        try {
            $stats = $service->exportJsonl($path, $bundlesPath, $errorsPath, $sleep, $limit, $csvEncoding);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Строк данных всего', $stats['rows_total']],
                ['Выгружено бандлов (JSONL)', $stats['rows_exported']],
                ['Пропущено (секция)', $stats['rows_skipped_section']],
                ['Пропущено (пусто)', $stats['rows_skipped_empty']],
                ['Записано в errors CSV', $stats['rows_errors_csv']],
                ['Из них с исключением API', $stats['rows_api_exception']],
            ]
        );

        return self::SUCCESS;
    }
}
