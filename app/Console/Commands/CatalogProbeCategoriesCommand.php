<?php

namespace App\Console\Commands;

use App\Services\AutoPartsCatalogService;
use Illuminate\Console\Command;

class CatalogProbeCategoriesCommand extends Command
{
    protected $signature = 'catalog:probe-categories
        {make : Марка (как в каталоге, например Renault)}
        {model : Модель (например Laguna)}
        {--year= : Год модели, например 2006}
    ';

    protected $description = 'Показывает HTTP-трейс запросов категорий RapidAPI Auto Parts Catalog (как при VIN-подборе)';

    public function handle(AutoPartsCatalogService $catalog): int
    {
        if (! $catalog->isConfigured()) {
            $this->error('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env.');

            return self::FAILURE;
        }

        $make = trim((string) $this->argument('make'));
        $model = trim((string) $this->argument('model'));
        $yearRaw = $this->option('year');
        $year = is_numeric($yearRaw) ? (int) $yearRaw : null;

        $this->line('Запрос категорий с HTTP-трейсом (внутренний флаг сервиса)…');
        $this->newLine();

        $result = $catalog->listCategoriesByVehicleDescriptor($make, $model, $year, true);

        $this->line('Сообщение: '.($result['message'] ?? ''));
        $this->line('manufacturer_id: '.($result['manufacturer_id'] ?? 'null'));
        $this->line('model_id: '.($result['model_id'] ?? 'null'));
        $this->line('type_id (vehicle): '.($result['type_id'] ?? 'null'));
        $this->line('Категорий после нормализации: '.count($result['categories'] ?? []));
        $this->line('Корней дерева (category_tree): '.count($result['category_tree'] ?? []));
        $tok = $result['category_rows_cache_token'] ?? null;
        $this->line('Кэш строк для пошагового UI (category_rows_cache_token): '.(is_string($tok) && $tok !== '' ? 'да (токен выдан)' : 'нет'));
        $this->newLine();

        $trace = $result['category_http_trace'] ?? [];
        $this->line((string) json_encode($trace, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
