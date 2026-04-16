<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use App\Services\CatalogProductImageDownloader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Перед повторным полным импортом CSV «Остатки»: очистка товаров и артефактов импорта.
 *
 * Удаление из `products` каскадом снимает строки в stocks, product_images, product_vehicle,
 * product_oem_numbers, product_cross_numbers, product_attributes, seller_products, price_list_items,
 * cart_items (позиции корзин с этими товарами). У заказов в order_items ссылка на товар nullable — станет NULL.
 *
 * Опция `--full`: дополнительно удаляет все категории и все бренды, затем создаёт заново бренд-заглушку
 * {@see Brand::platformUnknownFallback()} (нужен из‑за NOT NULL + restrict на products.brand_id).
 */
class CatalogResetForImportCommand extends Command
{
    protected $signature = 'catalog:reset-for-import
        {--force : Не спрашивать подтверждение}
        {--full : Удалить также все категории и бренды (кроме восстановленной заглушки «Без бренда»)}';

    protected $description = 'Удаляет все товары и очищает сироты Vehicle + плоские фото каталога в products/; опционально — категории и бренды';

    public function handle(): int
    {
        $full = (bool) $this->option('full');

        $confirmMessage = $full
            ? 'Удалить ВСЕ товары, ВСЕ категории и ВСЕ бренды (затем будет создан только служебный бренд «Без бренда»)? '
                .'Каскадно исчезнут остатки, фото товаров, OEM/кроссы, привязки к авто, позиции в корзинах. '
                .'В скидках category_id/product_id станут пустыми где разрешено. В заказах product_id станет пустым где разрешено. Продолжить?'
            : 'Удалить ВСЕ товары? Каскадно исчезнут остатки, фото товаров, OEM/кроссы, привязки к авто, позиции в корзинах. В заказах позиции останутся, но product_id станет пустым где разрешено. Продолжить?';

        if (! $this->option('force') && ! $this->confirm($confirmMessage)) {
            $this->warn('Отменено.');

            return self::FAILURE;
        }

        $productsBefore = Product::query()->count();
        $categoriesBefore = $full ? Category::query()->count() : 0;
        $brandsBefore = $full ? Brand::query()->count() : 0;

        DB::transaction(function () use ($full): void {
            Product::query()->delete();

            Vehicle::query()->whereDoesntHave('products')->delete();

            if ($full) {
                Category::query()->delete();
                Brand::query()->delete();
                Brand::platformUnknownFallback();
            }
        });

        $disk = Storage::disk('public');
        $legacyDir = 'products/catalog';
        if ($disk->exists($legacyDir)) {
            $disk->deleteDirectory($legacyDir);
        }
        $prefix = CatalogProductImageDownloader::CATALOG_FLAT_FILENAME_PREFIX;
        if ($disk->exists('products')) {
            foreach ($disk->files('products') as $relativePath) {
                $base = basename($relativePath);
                if (str_starts_with($base, $prefix)) {
                    $disk->delete($relativePath);
                }
            }
        }

        $this->info("Удалено товаров: {$productsBefore}. Осталось Vehicle: ".Vehicle::query()->count().'.');
        if ($full) {
            $this->info("Удалено категорий: {$categoriesBefore}. Удалено брендов (все строки): {$brandsBefore}. Сейчас брендов в БД: ".Brand::query()->count().' (включая заглушку).');
        }
        $this->comment('Удалены файлы products/'.$prefix.'* и каталог products/catalog (если был). Остальные файлы в products/ не трогаем.');
        $this->newLine();
        $this->info('Дальше: php artisan import:remains-csv путь/к/файлу.csv');

        return self::SUCCESS;
    }
}
