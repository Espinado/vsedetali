<?php

namespace App\Console\Commands;

use App\Services\StockCsvEnrichmentService;
use Illuminate\Console\Command;

class EnrichStockCsvFromCatalogCommand extends Command
{
    protected $signature = 'stock:enrich-catalog
        {path : Абсолютный или относительный путь к CSV «Остатки» (UTF-8)}
        {--output= : Файл результата (по умолчанию: рядом с исходным, суффикс -enriched.csv)}
        {--limit=0 : Обработать только первые N строк с артикулом (для теста)}
        {--sleep=150 : Пауза между запросами к API, мс (снижает риск лимита RapidAPI)}
        {--resume : Не дергать API для артикулов, уже есть в выходном CSV с источником oem/article (путь: --output или тот же по умолчанию)}
        {--resume-from= : Взять кэш строк из другого enriched CSV}
        {--only-skus-file= : API только для этих артикулов; остальные строки — из --resume или с пустыми доп. колонками (построчно UTF-8; # — комментарий)}
        {--not-found-output= : Куда писать список «не найдено» (по умолчанию рядом с -enriched.csv)}
        {--no-not-found-list : Не создавать *-not-found.txt}';

    protected $description = 'По артикулу из CSV «Остатки»: категория, производители OEM, аналоги (кросс), применимость к авто; строки без детали в каталоге не попадают в выходной файл';

    public function handle(StockCsvEnrichmentService $service): int
    {
        $rawPath = $this->argument('path');
        $path = str_starts_with($rawPath, '/') || (strlen($rawPath) > 2 && ctype_alpha($rawPath[0]) && $rawPath[1] === ':')
            ? $rawPath
            : base_path(trim($rawPath, '/\\'));

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $out = $this->option('output');
        if (! is_string($out) || $out === '') {
            $out = preg_replace('/\.csv$/iu', '', $path).'-enriched.csv';
        }
        if (! is_string($out) || $out === '') {
            $this->error('Не удалось сформировать путь к выходному файлу.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));

        $resumeFromRaw = $this->option('resume-from');
        $resumeFrom = null;
        if (is_string($resumeFromRaw) && $resumeFromRaw !== '') {
            $resumeFrom = str_starts_with($resumeFromRaw, '/') || (strlen($resumeFromRaw) > 2 && ctype_alpha($resumeFromRaw[0]) && $resumeFromRaw[1] === ':')
                ? $resumeFromRaw
                : base_path(trim($resumeFromRaw, '/\\'));
        }
        if ($this->option('resume')) {
            if ($resumeFrom === null && is_file($out)) {
                $resumeFrom = $out;
            } elseif ($resumeFrom === null) {
                $this->warn('Флаг --resume: выходной файл ещё не существует, продолжать не с чего (будет полный прогон).');
            }
        }

        $onlySkusRaw = $this->option('only-skus-file');
        $onlySkus = null;
        if (is_string($onlySkusRaw) && $onlySkusRaw !== '') {
            $onlySkus = str_starts_with($onlySkusRaw, '/') || (strlen($onlySkusRaw) > 2 && ctype_alpha($onlySkusRaw[0]) && $onlySkusRaw[1] === ':')
                ? $onlySkusRaw
                : base_path(trim($onlySkusRaw, '/\\'));
            if (! is_file($onlySkus)) {
                $this->error("Файл списка SKU не найден: {$onlySkus}");

                return self::FAILURE;
            }
        }

        $notFoundOutRaw = $this->option('not-found-output');
        $notFoundOut = null;
        if (is_string($notFoundOutRaw) && $notFoundOutRaw !== '') {
            $notFoundOut = str_starts_with($notFoundOutRaw, '/') || (strlen($notFoundOutRaw) > 2 && ctype_alpha($notFoundOutRaw[0]) && $notFoundOutRaw[1] === ':')
                ? $notFoundOutRaw
                : base_path(trim($notFoundOutRaw, '/\\'));
        }

        $options = [
            'resume_from_csv' => $resumeFrom,
            'only_skus_file' => $onlySkus,
            'write_not_found_list' => ! $this->option('no-not-found-list'),
            'not_found_output_path' => $notFoundOut,
        ];

        $this->info("Вход:  {$path}");
        $this->info("Выход: {$out}");
        if ($resumeFrom !== null) {
            $this->comment("Продолжение с кэша: {$resumeFrom}");
        }
        if ($onlySkus !== null) {
            $this->comment("Только SKU из файла: {$onlySkus}");
        }

        try {
            $stats = $service->enrichToFile($path, $out, $limit, $sleep, $options);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Строк в файле (после заголовка)', $stats['rows_total']],
                ['Взято из кэша (без API)', $stats['rows_resumed']],
                ['Новых записано в выход (найдено в каталоге)', $stats['rows_enriched']],
                ['Строк без API (не в --only-skus-file, пустые доп. колонки)', $stats['rows_passthrough_only_sku_filter']],
                ['Пропущено (секции DSLK/HongQi/…)', $stats['rows_skipped_section']],
                ['Пропущено (пустой артикул/наименование)', $stats['rows_skipped_empty']],
                ['Пропущено (деталь не найдена в каталоге)', $stats['rows_skipped_not_found']],
                ['Ошибок API', $stats['errors']],
            ]
        );

        if (! empty($stats['not_found_list_path'])) {
            $this->comment('Список не найденных в каталоге: '.$stats['not_found_list_path'].' (артикул[табкод])');
        }

        $this->newLine();
        $this->info('В файл попадают только строки, по которым деталь найдена в каталоге. Колонки: категория, подкатегория, производители OEM, аналоги aftermarket (кросс), сводка применимости, примеры марки/модели/кузова/годов/объёма, полнота полей (ok / partial), источник (oem / article).');
        $this->comment('Повторный прогон: php artisan stock:enrich-catalog "…csv" --resume --sleep=150  или только не найденные: --only-skus-file="…-not-found.txt"');

        return self::SUCCESS;
    }
}
