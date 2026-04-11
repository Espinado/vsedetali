<?php

namespace App\Console\Commands;

use App\Services\ProductZeroStockPriceCsvExchange;
use Illuminate\Console\Command;

class ProductsExportZeroStockPriceCsvCommand extends Command
{
    protected $signature = 'products:export-zero-stock-price-csv
        {path=storage/app/products_zero_stock_or_price.csv : Путь к CSV (от корня проекта или абсолютный)}';

    protected $description = 'Экспорт в CSV товаров с нулевой продажной ценой или без остатка на основном складе (для правки и импорта обратно)';

    public function handle(ProductZeroStockPriceCsvExchange $exchange): int
    {
        $rawPath = trim((string) $this->argument('path'), " \t\n\r\0\x0B\xC2\xA0");
        $path = $this->resolvePath($rawPath);

        try {
            $wh = $exchange->defaultPlatformWarehouse();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Склад для остатков: #{$wh->id} «{$wh->name}» (code: ".($wh->code ?? '—').')');
        $this->info('Колонки: product_id, oem_code, sku, name, cost_price, price, quantity');
        $this->newLine();

        try {
            $count = $exchange->exportToPath($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Записано строк (товаров): {$count}");
        $this->info("Файл: {$path}");

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
