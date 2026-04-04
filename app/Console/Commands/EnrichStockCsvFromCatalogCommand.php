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
        {--sleep=150 : Пауза между запросами к API, мс (снижает риск лимита RapidAPI)}';

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

        $this->info("Вход:  {$path}");
        $this->info("Выход: {$out}");

        try {
            $stats = $service->enrichToFile($path, $out, $limit, $sleep);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Строк в файле (после заголовка)', $stats['rows_total']],
                ['Строк с артикулом записано в выход (найдено в каталоге)', $stats['rows_enriched']],
                ['Пропущено (секции DSLK/HongQi/…)', $stats['rows_skipped_section']],
                ['Пропущено (пустой артикул/наименование)', $stats['rows_skipped_empty']],
                ['Пропущено (деталь не найдена в каталоге)', $stats['rows_skipped_not_found']],
                ['Ошибок API', $stats['errors']],
            ]
        );

        $this->newLine();
        $this->info('В файл попадают только строки, по которым деталь найдена в каталоге. Колонки: категория, подкатегория, производители OEM, аналоги aftermarket (кросс), сводка применимости, примеры марки/модели/кузова/годов/объёма, полнота полей (ok / partial), источник (oem / article).');

        return self::SUCCESS;
    }
}
