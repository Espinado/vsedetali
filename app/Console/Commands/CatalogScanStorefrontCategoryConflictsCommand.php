<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\CatalogStorefrontCategoryConflictDetector;
use App\Support\CatalogStorefrontCategoryModerationLogger;
use Illuminate\Console\Command;

/**
 * Поиск уже сохранённых в БД конфликтов «название товара ↔ категория витрины».
 */
class CatalogScanStorefrontCategoryConflictsCommand extends Command
{
    protected $signature = 'catalog:scan-storefront-category-conflicts
        {--limit=0 : Максимум проверенных товаров (0 = без лимита)}
        {--log : Дозаписать найденные строки в файл модерации (как при импорте)}';

    protected $description = 'Ищет товары с подозрительным сочетанием названия и категории витрины';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $doLog = (bool) $this->option('log');

        $checked = 0;
        $hits = 0;
        $sample = [];

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->with(['category.parent'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$checked, &$hits, &$sample, $limit, $doLog): bool {
                foreach ($chunk as $product) {
                    if ($limit > 0 && $checked >= $limit) {
                        return false;
                    }
                    $checked++;
                    $leaf = $product->category;
                    if ($leaf === null) {
                        continue;
                    }
                    $parent = $leaf->parent;
                    $path = $parent !== null
                        ? $parent->name.' / '.$leaf->name
                        : $leaf->name;

                    $reason = CatalogStorefrontCategoryConflictDetector::detectForAssignedCategory(
                        (string) $product->name,
                        $path
                    );
                    if ($reason === null) {
                        continue;
                    }
                    $hits++;
                    if (count($sample) < 25) {
                        $sample[] = [$product->id, $product->sku, $reason, mb_substr((string) $product->name, 0, 60), $path];
                    }
                    if ($doLog) {
                        $parts = explode(' / ', $path, 2);
                        $main = $parts[0] ?? '';
                        $sub = $parts[1] ?? '';
                        CatalogStorefrontCategoryModerationLogger::log(
                            $reason,
                            (int) $product->id,
                            (string) $product->sku,
                            (string) $product->name,
                            $main,
                            $sub,
                            $path,
                            'existing_product_scan',
                        );
                    }
                }

                return true;
            });

        $this->info("Проверено товаров: {$checked}, найдено конфликтов (эвристика): {$hits}");
        if ($hits > 0) {
            $this->table(['id', 'sku', 'reason', 'name (обрезка)', 'категория'], $sample);
            $this->comment('Файл модерации по умолчанию: '.CatalogStorefrontCategoryModerationLogger::path());
            if (! $doLog) {
                $this->comment('Повторите с --log, чтобы дозаписать найденные строки в TSV.');
            }
        }

        return self::SUCCESS;
    }
}
