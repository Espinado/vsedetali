<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Безопасные автоисправления для очевидных мискатегорий на витрине.
 */
class CatalogFixStorefrontCategoryMismatchesCommand extends Command
{
    protected $signature = 'catalog:fix-storefront-category-mismatches
        {--limit=0 : Максимум обработанных кандидатов (0 = без лимита)}
        {--apply : Применить изменения (без флага только preview)}';

    protected $description = 'Исправляет очевидные несоответствия «название товара ↔ категория» по консервативным правилам';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');

        $targetBySlug = Category::query()
            ->whereIn('slug', ['td-kardannyi-val', 'td-datciki'])
            ->get()
            ->keyBy('slug');
        foreach (['td-kardannyi-val', 'td-datciki'] as $requiredSlug) {
            if (! $targetBySlug->has($requiredSlug)) {
                $this->error("Категория {$requiredSlug} не найдена, исправления невозможны.");

                return self::FAILURE;
            }
        }

        $checked = 0;
        $candidates = 0;
        $changed = 0;
        $sample = [];

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->with('category')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use (&$checked, &$candidates, &$changed, &$sample, $limit, $apply, $targetBySlug): bool {
                foreach ($chunk as $product) {
                    if ($limit > 0 && $checked >= $limit) {
                        return false;
                    }
                    $checked++;

                    $current = $product->category;
                    if ($current === null) {
                        continue;
                    }
                    $name = mb_strtolower(trim((string) $product->name));
                    $cat = mb_strtolower(trim((string) $current->name));
                    $target = null;
                    $reason = '';

                    // Консервативное правило #1: "муфта/кардан" не должна жить в шарнирных категориях.
                    $isCardanCoupling = Str::contains($name, 'кардан')
                        || (Str::contains($name, 'муфт') && Str::contains($name, 'вала'));
                    $looksCvJointCategory = Str::contains($cat, 'шарнир') || Str::contains($cat, 'шрус');
                    if ($isCardanCoupling && $looksCvJointCategory) {
                        $target = $targetBySlug->get('td-kardannyi-val');
                        $reason = 'cardan_coupling_from_cvjoint_to_cardan';
                    }

                    // Консервативное правило #2: датчики в "Краски. лаки" переносим в "Датчики".
                    if ($target === null) {
                        $looksSensor = Str::contains($name, 'датчик')
                            || Str::contains($name, 'abs')
                            || Str::contains($name, 'абс');
                        if ($looksSensor && (string) $current->slug === 'td-kraski-laki') {
                            $target = $targetBySlug->get('td-datciki');
                            $reason = 'sensor_from_paints_to_sensors';
                        }
                    }

                    if (! $target instanceof Category) {
                        continue;
                    }
                    if ((int) $current->id === (int) $target->id) {
                        continue;
                    }

                    $candidates++;
                    if (count($sample) < 30) {
                        $sample[] = [
                            $product->id,
                            $product->sku,
                            Str::limit((string) $product->name, 70),
                            (string) $current->name,
                            (string) $target->name,
                            $reason,
                            $apply ? 'updated' : 'preview',
                        ];
                    }

                    if ($apply) {
                        $product->category_id = $target->id;
                        $product->save();
                        $changed++;
                    }
                }

                return true;
            });

        $this->info("Проверено: {$checked}; кандидатов: {$candidates}; ".($apply ? "обновлено: {$changed}" : 'режим preview, без записи'));
        if ($sample !== []) {
            $this->table(['id', 'sku', 'name', 'from', 'to', 'reason', 'mode'], $sample);
        }
        if (! $apply) {
            $this->comment('Для применения повторите с --apply');
        }

        return self::SUCCESS;
    }
}

