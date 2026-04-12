<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryCatalogSlug;
use App\Models\ProductAttribute;
use App\Models\ProductCrossNumber;
use App\Models\ProductOemNumber;
use Illuminate\Support\Str;

/**
 * Заполнение product_oem_numbers, product_cross_numbers, product_attributes из SKU и ответа {@see AutoPartsCatalogService::lookupEnrichedForStock}.
 */
class CatalogProductMetadataSyncService
{
    /**
     * Основной номер из артикула (часть SKU до «/») — всегда кладём в product_oem_numbers при импорте.
     */
    public function ensurePrimaryOemFromSku(Product $product): bool
    {
        $part = $this->primaryPartNumberFromSku($product);
        if ($part === '') {
            return false;
        }

        $row = ProductOemNumber::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'oem_number' => $part,
            ],
            []
        );

        return $row->wasRecentlyCreated;
    }

    /**
     * Дополнительные OEM, кроссы и атрибуты категории из обогащения RapidAPI.
     *
     * @param  array<string, mixed>  $enriched
     * @return array{oem_added: int, cross_added: int, attributes_upserted: int}
     */
    public function syncFromEnrichment(Product $product, array $enriched): array
    {
        $out = ['oem_added' => 0, 'cross_added' => 0, 'attributes_upserted' => 0];

        $this->ensurePrimaryOemFromSku($product);

        $oemList = $enriched['oem_suppliers'] ?? [];
        if (is_array($oemList)) {
            foreach ($oemList as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $no = trim((string) ($row['articleNo'] ?? ''));
                if ($no === '') {
                    continue;
                }
                $no = Str::limit($no, 100, '');
                $created = ProductOemNumber::query()->firstOrCreate(
                    ['product_id' => $product->id, 'oem_number' => $no],
                    []
                );
                if ($created->wasRecentlyCreated) {
                    $out['oem_added']++;
                }
            }
        }

        $crossList = $enriched['cross_analogs'] ?? [];
        if (is_array($crossList)) {
            foreach ($crossList as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $manufacturer = trim((string) ($row['crossManufacturerName'] ?? ''));
                if ($manufacturer === '') {
                    $manufacturer = trim((string) ($row['supplierName'] ?? ''));
                }
                $manufacturer = $manufacturer !== '' ? Str::limit($manufacturer, 255, '') : '';

                foreach (['articleNo', 'crossNumber'] as $key) {
                    $cn = trim((string) ($row[$key] ?? ''));
                    if ($cn === '') {
                        continue;
                    }
                    $cn = Str::limit($cn, 100, '');
                    $record = ProductCrossNumber::query()->firstOrNew([
                        'product_id' => $product->id,
                        'cross_number' => $cn,
                    ]);
                    $wasNew = ! $record->exists;
                    if ($manufacturer !== '') {
                        $record->manufacturer_name = $manufacturer;
                    }
                    if ($wasNew) {
                        $record->save();
                        $out['cross_added']++;
                    } elseif ($record->isDirty()) {
                        $record->save();
                    }
                }
            }
        }

        $main = trim((string) ($enriched['category_main'] ?? ''));
        $sub = trim((string) ($enriched['category_sub'] ?? ''));
        if ($main !== '' || $sub !== '') {
            $val = $main.($sub !== '' ? ' / '.$sub : '');
            ProductAttribute::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'name' => 'Категория (каталог)',
                ],
                [
                    'value' => Str::limit($val, 500, ''),
                    'sort' => 0,
                ]
            );
            $out['attributes_upserted']++;
        }

        $source = (string) ($enriched['source'] ?? 'none');
        if ($source !== 'none') {
            $label = $source === 'oem' ? 'OEM' : 'Артикул';
            ProductAttribute::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'name' => 'Источник каталога',
                ],
                [
                    'value' => $label,
                    'sort' => 1,
                ]
            );
            $out['attributes_upserted']++;
        }

        return $out;
    }

    /**
     * Привязка товара к дереву {@see Category} по полям category_main / category_sub из TecDoc (для витрины /catalog).
     */
    public function assignStorefrontCategoryFromEnrichment(Product $product, array $enriched): bool
    {
        $main = trim((string) ($enriched['category_main'] ?? ''));
        $sub = trim((string) ($enriched['category_sub'] ?? ''));
        if ($main === '' && $sub === '') {
            return false;
        }
        if ($main === '') {
            $main = $sub;
            $sub = '';
        }

        $parent = $this->firstOrCreateCategoryByNameUnderParent($main, null);
        $leaf = $sub !== '' ? $this->firstOrCreateCategoryByNameUnderParent($sub, $parent->id) : $parent;

        if ((int) $product->category_id === (int) $leaf->id) {
            return false;
        }

        $product->category_id = $leaf->id;
        $product->save();

        return true;
    }

    protected function firstOrCreateCategoryByNameUnderParent(string $name, ?int $parentId): Category
    {
        $name = Str::limit(trim($name), 255, '');
        if ($name === '') {
            throw new \InvalidArgumentException('Пустое имя категории');
        }

        $existing = Category::query()
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $slug = CategoryCatalogSlug::uniqueTechnicalPrefixed($name, $parentId);

        return Category::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'sort' => 0,
        ]);
    }

    protected function primaryPartNumberFromSku(Product $product): string
    {
        $raw = trim(Str::limit(explode('/', (string) $product->sku, 2)[0], 100, ''));

        return $raw;
    }
}
