<?php

namespace App\Console\Commands;

use App\Services\GeelyVesnaSpringExcelImportService;
use Illuminate\Console\Command;

class ImportGeelyVesnaSpringExcelCommand extends Command
{
    protected $signature = 'import:geely-vesna-xlsx
        {path : Путь к .xlsx (абсолютный или относительно base_path)}
        {--dry-run : Только подсчёт строк, без записи в БД}';

    protected $description = 'Импорт XLSX «ДЖИЛИ ВЕСНА / БАМБУК»: жёлтая строка + столбец B = категория; A = марка и модель; C = артикул; D = остаток';

    public function handle(GeelyVesnaSpringExcelImportService $service): int
    {
        $path = $this->resolvePath($this->argument('path'));

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== 'xlsx' && $ext !== 'xlsm') {
            $this->warn('Ожидается расширение .xlsx / .xlsm');
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Режим dry-run: БД не изменяется.');
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
                ['Строк листа обработано', $stats['rows']],
                ['Строк-категорий (жёлтые / B без артикула)', $stats['category_rows']],
                ['Импортировано позиций', $stats['imported']],
                ['Пропущено', $stats['skipped']],
                ['Создано подкатегорий', $stats['created_categories']],
                ['Создано товаров', $stats['created_products']],
                ['Обновлено товаров', $stats['updated_products']],
                ['Создано авто (Vehicle)', $stats['created_vehicles']],
                ['Новых привязок товар–авто', $stats['attached_vehicles']],
            ]
        );

        $this->newLine();
        $this->comment('Если команда ругается на PhpSpreadsheet: в папке проекта выполните composer update');

        return self::SUCCESS;
    }

    private function resolvePath(string $raw): string
    {
        if ($raw !== '' && ($raw[0] === '/' || (strlen($raw) > 2 && ctype_alpha($raw[0]) && $raw[1] === ':'))) {
            return $raw;
        }

        return base_path(trim($raw, '/\\'));
    }
}
