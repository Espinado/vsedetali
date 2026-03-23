<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\RemainsStockCsvImportService;
use Illuminate\Console\Command;

class ImportRemainsStockCommand extends Command
{
    protected $signature = 'import:remains-csv
        {path : Путь к CSV (UTF-8), отчёт «Остатки»}
        {--dry-run : Без записи в БД}';

    protected $description = 'Импорт остатков из CSV «Остатки»: секции (Марки/, Производители/, DSLK, Б/У Дефект…), Доступно, себестоимость, цена; существующий SKU не обновляется';

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

        $importedLabel = $dryRun
            ? 'Новых позиций (расчёт dry-run, в БД не пишется)'
            : 'Импортировано новых позиций';

        $createdLabel = $dryRun
            ? 'Создалось бы товаров (не создано — dry-run)'
            : 'Создано товаров';

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Строк в файле (fgetcsv)', $stats['rows']],
                ['Пропущено (пустые строки и т.п.)', $stats['skipped']],
                [$importedLabel, $stats['imported']],
                [$createdLabel, $stats['created_products']],
                ['Пропущено (SKU уже в базе)', $stats['skipped_existing']],
                ['Создано автомобилей (Vehicle)', $stats['created_vehicles']],
                ['Привязок товар–авто', $stats['attached_vehicles']],
            ]
        );

        $this->newLine();
        if ($dryRun) {
            $this->warn('Dry-run: таблица products не изменялась. Для записи в БД запустите без флага --dry-run.');
        } else {
            $this->info('Всего товаров в базе сейчас: '.Product::query()->count().'.');
            if ($stats['created_products'] === 0 && $stats['skipped_existing'] > 0) {
                $this->comment('Ни одного нового товара: все SKU из файла уже есть в базе (существующие не обновляются).');
            }
        }

        return self::SUCCESS;
    }
}
