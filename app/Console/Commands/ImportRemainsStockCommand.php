<?php

namespace App\Console\Commands;

use App\Services\RemainsStockCsvImportService;
use Illuminate\Console\Command;

class ImportRemainsStockCommand extends Command
{
    protected $signature = 'import:remains-csv
        {path : Путь к CSV (UTF-8), отчёт «Остатки»}
        {--dry-run : Без записи в БД}';

    protected $description = 'Импорт остатков из CSV (Код, Артикул, Наименование, Резерв, Остаток, себестоимость, цена продажи, дней на складе)';

    public function handle(RemainsStockCsvImportService $service): int
    {
        $rawPath = $this->argument('path');
        $path = str_starts_with($rawPath, '/') || (strlen($rawPath) > 2 && ctype_alpha($rawPath[0]) && $rawPath[1] === ':')
            ? $rawPath
            : base_path(trim($rawPath, '/\\'));

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Режим dry-run.');
        }

        $this->info("Файл: {$path}");

        try {
            $stats = $service->import($path, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Строк в файле (после открытия)', $stats['rows']],
                ['Пропущено', $stats['skipped']],
                ['Импортировано позиций', $stats['imported']],
                ['Новых товаров', $stats['created_products']],
                ['Обновлено товаров', $stats['updated_products']],
            ]
        );

        return self::SUCCESS;
    }
}
