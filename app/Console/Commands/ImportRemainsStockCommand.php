<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoPartsCatalogService;
use App\Services\RemainsStockCsvImportService;
use Illuminate\Console\Command;

class ImportRemainsStockCommand extends Command
{
    protected $signature = 'import:remains-csv
        {path : Путь к CSV (UTF-8), отчёт «Остатки»}
        {--dry-run : Без записи в БД}
        {--catalog-images : Принудительно включить загрузку фото из каталога (по умолчанию уже включено, если не задано REMAINS_IMPORT_CATALOG_IMAGES=false)}';

    protected $description = 'Импорт остатков из CSV «Остатки»: секции (Марки/, Производители/, DSLK, Б/У Дефект…), Доступно, себестоимость, цена; существующий SKU не обновляется';

    public function handle(RemainsStockCsvImportService $service, AutoPartsCatalogService $catalog): int
    {
        $rawPath = trim((string) $this->argument('path'), " \t\n\r\0\x0B\xC2\xA0");
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

        $downloadImagesOverride = (bool) $this->option('catalog-images');

        try {
            $stats = $service->import($path, $dryRun, $downloadImagesOverride ? true : null);
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
                ['Фото из каталога (сохранено)', $stats['catalog_images_attached']],
                ['Ошибок API/скачивания фото', $stats['catalog_images_failed']],
                ['Нет URL фото в ответе API (s3image)', $stats['catalog_images_no_url']],
                ['Ключ RapidAPI не задан (фото не запрашивались)', $stats['catalog_images_no_api']],
                ['Новых привязок авто из каталога (марка/модель)', $stats['catalog_vehicles_attached']],
                ['Ошибок обогащения каталогом (API)', $stats['catalog_enrichment_failed']],
                ['Основной OEM из SKU → product_oem_numbers', $stats['catalog_primary_oem_added']],
                ['Доп. OEM из ответа API', $stats['catalog_metadata_oem_extra']],
                ['Кроссы → product_cross_numbers', $stats['catalog_metadata_cross']],
                ['Атрибуты каталога → product_attributes', $stats['catalog_metadata_attributes']],
                ['Категория витрины → products.category_id (TecDoc)', $stats['catalog_storefront_categories']],
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
            if ($stats['created_products'] > 0) {
                if (! $catalog->isConfigured() && ($stats['catalog_images_no_api'] > 0 || $stats['catalog_images_attached'] === 0)) {
                    $this->warn('Загрузка фото: задайте RAPIDAPI_AUTO_PARTS_KEY в .env и выполните php artisan config:clear.');
                } elseif ($stats['catalog_images_no_url'] > 0 && $stats['catalog_images_attached'] === 0 && $catalog->isConfigured()) {
                    $this->comment('API не вернул ссылку на фото для новых позиций: проверьте артикул (для поиска берётся часть SKU до символа «/»). Убедитесь, что в ответе OEM/article есть поле s3image.');
                }
            }
        }

        return self::SUCCESS;
    }
}
