<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogProductMetadataSyncService;
use Illuminate\Console\Command;

/**
 * Заполняет product_oem_numbers основным номером из SKU (часть до «/») для товаров, где записей ещё нет.
 */
class ProductsBackfillPrimaryOemCommand extends Command
{
    protected $signature = 'products:backfill-primary-oem
        {--limit=0 : Обработать не больше N товаров (0 = все)}';

    protected $description = 'Добавляет в product_oem_numbers артикул из SKU для товаров без OEM-записей';

    public function handle(CatalogProductMetadataSyncService $metadataSync): int
    {
        $limit = max(0, (int) $this->option('limit'));

        $query = Product::query()
            ->whereDoesntHave('oemNumbers')
            ->orderBy('id');

        $processed = 0;
        $added = 0;

        foreach ($query->lazy(100) as $product) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }
            if ($metadataSync->ensurePrimaryOemFromSku($product)) {
                $added++;
            }
            $processed++;
        }

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Проверено товаров без OEM', $processed],
                ['Создано записей product_oem_numbers', $added],
            ]
        );

        return self::SUCCESS;
    }
}
