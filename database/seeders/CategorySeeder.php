<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['name' => 'Тормозная система', 'slug' => 'brake-system', 'children' => [
                ['name' => 'Тормозные колодки', 'slug' => 'brake-pads'],
                ['name' => 'Тормозные диски', 'slug' => 'brake-discs'],
                ['name' => 'Тормозные суппорты', 'slug' => 'brake-calipers'],
            ]],
            ['name' => 'Подвеска', 'slug' => 'suspension', 'children' => [
                ['name' => 'Амортизаторы', 'slug' => 'shock-absorbers'],
                ['name' => 'Стойки стабилизатора', 'slug' => 'stabilizer-links'],
                ['name' => 'Шаровые опоры', 'slug' => 'ball-joints'],
            ]],
            ['name' => 'Двигатель', 'slug' => 'engine', 'children' => [
                ['name' => 'Фильтры', 'slug' => 'filters'],
                ['name' => 'Ремни и комплекты ГРМ', 'slug' => 'timing-belts'],
                ['name' => 'Свечи зажигания', 'slug' => 'spark-plugs'],
            ]],
            ['name' => 'Электрика', 'slug' => 'electrical', 'children' => [
                ['name' => 'Аккумуляторы', 'slug' => 'batteries'],
                ['name' => 'Стартеры и генераторы', 'slug' => 'starters-generators'],
            ]],
        ];

        foreach ($tree as $sort => $root) {
            $children = $root['children'] ?? [];
            unset($root['children']);

            $rootCat = Category::updateOrCreate(
                ['slug' => $root['slug']],
                [
                    'parent_id' => null,
                    'name' => $root['name'],
                    'sort' => ($sort + 1) * 10,
                    'is_active' => true,
                ]
            );

            foreach ($children as $i => $child) {
                Category::updateOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'parent_id' => $rootCat->id,
                        'name' => $child['name'],
                        'sort' => ($i + 1) * 10,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
