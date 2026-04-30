<?php

namespace App\Console\Commands;

use App\Services\ProductImportTemplateXlsxBuilder;
use Illuminate\Console\Command;

class ProductsTemplateXlsxCommand extends Command
{
    protected $signature = 'products:template-xlsx
        {path? : Путь к выходному файлу .xlsx (по умолчанию storage/app/templates/products_import_template.xlsx)}';

    protected $description = 'Создаёт Excel-шаблон для массовой загрузки товаров по полям карточки товара';

    public function handle(ProductImportTemplateXlsxBuilder $builder): int
    {
        $path = $this->resolvePath((string) ($this->argument('path') ?? ''));

        $headers = [
            'external_id',
            'category_id',
            'category_name',
            'brand_id',
            'brand_name',
            'code',
            'sku',
            'name',
            'short_description',
            'description',
            'weight_kg',
            'price',
            'cost_price',
            'vat_rate',
            'is_active',
            'vehicle_ids',
            'vehicle_labels',
            'oem_numbers',
            'image_urls',
            'meta_title',
            'meta_description',
        ];

        $hints = [
            'Ваш внешний id (опционально, для маппинга)',
            'ID категории (приоритетнее category_name)',
            'Название категории, если не знаете id',
            'ID бренда (приоритетнее brand_name)',
            'Название бренда, если не знаете id',
            'Внутренний код товара',
            'Артикул',
            'Наименование (обязательно)',
            'Короткое описание',
            'Полное описание',
            'Вес в кг (пример: 1.250)',
            'Продажная цена (обязательно)',
            'Себестоимость',
            'Ставка НДС (пример: 20)',
            '1 или 0',
            'ID автомобилей через запятую',
            'Лейблы авто через ; (make model year)',
            'OEM номера через запятую',
            'URL изображений через запятую',
            'Meta title',
            'Meta description',
        ];

        $example = [
            'ERP-100045',
            '12',
            'Электрика',
            '5',
            'Bosch',
            'A-7788',
            '0 986 479 365',
            'Датчик ABS передний левый',
            'Для Renault Laguna',
            'Оригинальный датчик ABS для передней оси.',
            '0.320',
            '3490.00',
            '2210.00',
            '20',
            '1',
            '15473',
            'Renault Laguna 2006',
            '8200675674, 8200675675',
            'https://cdn.example.com/p/abs-1.jpg, https://cdn.example.com/p/abs-2.jpg',
            'Датчик ABS Renault Laguna',
            'Купить датчик ABS для Renault Laguna 2006.',
        ];

        try {
            $builder->build($path, $headers, $hints, $example);
        } catch (\Throwable $e) {
            $this->error('Не удалось создать шаблон: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Шаблон Excel создан: '.$path);

        return self::SUCCESS;
    }

    protected function resolvePath(string $rawPath): string
    {
        $clean = trim($rawPath);
        if ($clean === '') {
            return storage_path('app/templates/products_import_template.xlsx');
        }
        if (str_starts_with($clean, '/') || (strlen($clean) > 2 && ctype_alpha($clean[0]) && $clean[1] === ':')) {
            return $clean;
        }

        return base_path(trim($clean, '/\\'));
    }
}

