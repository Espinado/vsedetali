<?php

namespace App\Console\Commands;

use App\Services\GeelyBambooImportService;
use Illuminate\Console\Command;

class ImportGeelyBambooCommand extends Command
{
    protected $signature = 'import:geely-bamboo
        {path : Путь к CSV (относительно base_path() или абсолютный)}
        {--dry-run : Только посчитать строки, без записи в БД}';

    protected $description = 'Импорт прайса Geely (Бамбук): марка/модель из 1–2 слова 1-го столбца, наименование, артикул, остаток';

    public function handle(GeelyBambooImportService $service): int
    {
        $rawPath = $this->argument('path');
        $path = $this->resolvePath($rawPath);

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Режим dry-run: данные в БД не меняются.');
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
                ['Строк в файле', $stats['rows']],
                ['Пропущено', $stats['skipped']],
                ['Импортировано строк данных', $stats['imported']],
                ['Создано товаров', $stats['created_products']],
                ['Обновлено товаров', $stats['updated_products']],
                ['Создано автомобилей (Vehicle)', $stats['created_vehicles']],
                ['Новых привязок товар–авто', $stats['attached_vehicles']],
            ]
        );

        return self::SUCCESS;
    }

    private function resolvePath(string $raw): string
    {
        if ($raw !== '' && ($raw[0] === '/' || (strlen($raw) > 2 && $raw[1] === ':' && ($raw[2] === '\\' || $raw[2] === '/')))) {
            return $raw;
        }

        return base_path(trim($raw, '/\\'));
    }
}
