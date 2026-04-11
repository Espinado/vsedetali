<?php

namespace App\Console\Commands;

use App\Services\ProductZeroStockPriceCsvExchange;
use Illuminate\Console\Command;

class ProductsImportZeroStockPriceCsvCommand extends Command
{
    protected $signature = 'products:import-zero-stock-price-csv
        {path : Путь к CSV (от корня проекта или абсолютный)}
        {--dry-run : Только проверка, без записи в БД}
        {--delimiter=, : Разделитель полей: запятая или ; (Excel в регионе EU)}';

    protected $description = 'Импорт из CSV: строка ищется по product_id и/или oem_code (или sku); обновляются cost_price, price, quantity на основном складе (reserved не трогаем; пустая ячейка = не менять поле)';

    public function handle(ProductZeroStockPriceCsvExchange $exchange): int
    {
        $rawPath = trim((string) $this->argument('path'), " \t\n\r\0\x0B\xC2\xA0");
        $path = $this->resolvePath($rawPath);

        if (! is_file($path)) {
            $this->error("Файл не найден: {$path}");

            return self::FAILURE;
        }

        $delimiter = (string) $this->option('delimiter');
        $delimiter = match ($delimiter) {
            ';', ',' => $delimiter,
            default => ',',
        };

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Режим dry-run: изменения в БД не применяются.');
        }

        try {
            $stats = $exchange->importFromPath($path, $delimiter, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Показатель', 'Значение'],
            [
                ['Обновлено товаров (цена)', $stats['updated_products']],
                ['Операций по остаткам (склад)', $stats['updated_stocks']],
                ['Пропущено строк', $stats['skipped']],
            ]
        );

        if ($stats['errors'] !== []) {
            $this->newLine();
            $this->warn('Сообщения:');
            foreach (array_slice($stats['errors'], 0, 30) as $msg) {
                $this->line($msg);
            }
            if (count($stats['errors']) > 30) {
                $this->line('… ещё '.(count($stats['errors']) - 30).' сообщений.');
            }
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $rawPath): string
    {
        if (str_starts_with($rawPath, '/') || (strlen($rawPath) > 2 && ctype_alpha($rawPath[0]) && $rawPath[1] === ':')) {
            return $rawPath;
        }

        return base_path(trim($rawPath, '/\\'));
    }
}
