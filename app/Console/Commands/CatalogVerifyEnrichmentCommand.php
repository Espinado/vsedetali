<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoPartsCatalogService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Проверка ответа RapidAPI до полного импорта: категория, фото, кроссы, применимость (марка/модель).
 */
class CatalogVerifyEnrichmentCommand extends Command
{
    protected $signature = 'catalog:verify-enrichment
        {article? : Один артикул (как часть SKU до «/»)}
        {--sample=0 : Проверить N случайных товаров из БД (по первому сегменту SKU)}
        {--sleep=200 : Пауза между запросами к API, мс}';

    protected $description = 'Пробный запрос lookupEnrichedForStock: категория TecDoc, URL фото, число кроссов и авто';

    public function handle(AutoPartsCatalogService $catalog): int
    {
        if (! $catalog->isConfigured()) {
            $this->error('Задайте RAPIDAPI_AUTO_PARTS_KEY в .env и выполните php artisan config:clear.');

            return self::FAILURE;
        }

        $articleArg = $this->argument('article');
        $sample = max(0, (int) $this->option('sample'));
        $sleepMs = max(0, (int) $this->option('sleep'));

        $articles = [];
        if ($articleArg !== null && trim((string) $articleArg) !== '') {
            $articles[] = trim(Str::limit(explode('/', (string) $articleArg, 2)[0], 100, ''));
        } elseif ($sample > 0) {
            foreach (Product::query()->active()->inRandomOrder()->limit($sample)->cursor() as $p) {
                $a = trim(Str::limit(explode('/', (string) $p->sku, 2)[0], 100, ''));
                if ($a !== '') {
                    $articles[] = $a;
                }
            }
            if ($articles === []) {
                $this->warn('В базе нет товаров для выборки.');

                return self::FAILURE;
            }
        } else {
            $this->error('Укажите артикул (позиционный аргумент) или --sample=N (случайные товары из БД).');

            return self::FAILURE;
        }

        $this->info('Что проверяем по каждому артикулу:');
        $this->line(' • категория TecDoc → в импорте: product_attributes + привязка к categories (если есть main/sub);');
        $this->line(' • URL фото → product_images (скачивание в storage/app/public/products/catalog/…);');
        $this->line(' • кроссы → product_cross_numbers;');
        $this->line(' • марка/модель → vehicles + product_vehicle.');
        $this->newLine();

        $rows = [];
        foreach ($articles as $i => $article) {
            if ($i > 0 && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            try {
                $e = $catalog->lookupEnrichedForStockWithCandidates($article, null);
            } catch (\Throwable $ex) {
                $rows[] = [
                    $article,
                    '—',
                    '—',
                    '—',
                    '—',
                    '—',
                    '—',
                    'Ошибка: '.$ex->getMessage(),
                ];

                continue;
            }

            $src = (string) ($e['source'] ?? 'none');
            $main = trim((string) ($e['category_main'] ?? ''));
            $sub = trim((string) ($e['category_sub'] ?? ''));
            $cat = $main.($sub !== '' ? ' / '.$sub : '');

            $img = trim((string) ($e['catalog_image_url'] ?? ''));
            $imgShort = $img !== '' ? 'да ('.Str::limit($img, 40, '…').')' : 'нет';

            $oemN = is_array($e['oem_suppliers'] ?? null) ? count($e['oem_suppliers']) : 0;
            $crossN = is_array($e['cross_analogs'] ?? null) ? count($e['cross_analogs']) : 0;
            $vehN = is_array($e['vehicles_normalized'] ?? null) ? count($e['vehicles_normalized']) : 0;

            $note = match (true) {
                $src === 'none' => 'API не вернул OEM/данные (часто китайский OEM)',
                $crossN === 0 && $vehN === 0 && $main === '' && $sub === '' && $img === '' => 'Только источник/поставщики',
                default => 'OK для записи в БД (часть полей может быть пустой)',
            };

            $rows[] = [
                Str::limit($article, 24),
                $src,
                Str::limit($cat, 36),
                $imgShort,
                (string) $oemN,
                (string) $crossN,
                (string) $vehN,
                Str::limit($note, 48),
            ];
        }

        $this->table(
            ['Артикул', 'Источник', 'Категория', 'Фото', 'OEM строк', 'Кроссов', 'Авто', 'Заметка'],
            $rows
        );

        $this->newLine();
        $this->comment('Импорт CSV пишет всё это только при REMAINS_IMPORT_SYNC_CATALOG_VEHICLES=true (один запрос lookup на новый товар).');

        return self::SUCCESS;
    }
}
