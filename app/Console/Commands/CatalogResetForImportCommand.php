<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Перед повторным полным импортом CSV «Остатки»: очистка товаров и артефактов импорта.
 *
 * Удаление из `products` каскадом снимает строки в stocks, product_images, product_vehicle,
 * product_oem_numbers, product_cross_numbers, product_attributes, seller_products, price_list_items,
 * cart_items (позиции корзин с этими товарами). У заказов в order_items ссылка на товар nullable — станет NULL.
 */
class CatalogResetForImportCommand extends Command
{
    protected $signature = 'catalog:reset-for-import
        {--force : Не спрашивать подтверждение}';

    protected $description = 'Удаляет все товары и очищает сироты Vehicle + папку фото импорта products/catalog';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'Удалить ВСЕ товары? Каскадно исчезнут остатки, фото товаров, OEM/кроссы, привязки к авто, позиции в корзинах. В заказах позиции останутся, но product_id станет пустым где разрешено. Продолжить?'
        )) {
            $this->warn('Отменено.');

            return self::FAILURE;
        }

        $productsBefore = Product::query()->count();

        DB::transaction(function () {
            Product::query()->delete();

            Vehicle::query()->whereDoesntHave('products')->delete();
        });

        $dir = 'products/catalog';
        if (Storage::disk('public')->exists($dir)) {
            Storage::disk('public')->deleteDirectory($dir);
        }

        $this->info("Удалено товаров: {$productsBefore}. Сирот Vehicle после очистки: ".Vehicle::query()->count().' (остались только с привязками, если были).');
        $this->comment('Папка storage/app/public/'.$dir.' очищена (фото из каталога RapidAPI). Ручные загрузки в products/ не трогаем.');
        $this->newLine();
        $this->info('Дальше: php artisan import:remains-csv путь/к/файлу.csv');

        return self::SUCCESS;
    }
}
