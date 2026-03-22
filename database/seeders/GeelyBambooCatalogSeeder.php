<?php

namespace Database\Seeders;

use App\Services\GeelyBambooImportService;
use Illuminate\Database\Seeder;

/**
 * Наполняет каталог из CSV «ДЖИЛИ ВЕСНА / БАМБУК» (файл в database/data/catalog_geely_bamboo.csv).
 * Правила пропуска строк — config/geely_bamboo_import.php
 */
class GeelyBambooCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/catalog_geely_bamboo.csv');

        if (! is_readable($path)) {
            $this->command?->warn('Каталог: пропуск — нет файла database/data/catalog_geely_bamboo.csv');

            return;
        }

        $stats = app(GeelyBambooImportService::class)->import($path, false);

        $this->command?->info(sprintf(
            'Каталог Geely Бамбук: импортировано %d строк, пропущено %d, товаров +%d / обновлено %d, авто +%d, привязок +%d.',
            $stats['imported'],
            $stats['skipped'],
            $stats['created_products'],
            $stats['updated_products'],
            $stats['created_vehicles'],
            $stats['attached_vehicles']
        ));
    }
}
