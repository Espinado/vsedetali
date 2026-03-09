<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::where('is_default', true)->first();
        if (!$warehouse) {
            return;
        }

        $brands = Brand::all()->keyBy('slug');
        $categories = Category::all()->keyBy('slug');

        $products = [
            ['name' => 'Колодки тормозные передние Bosch 0986AB4235', 'sku' => 'BOS-0986AB4235', 'category' => 'brake-pads', 'brand' => 'bosch', 'price' => 45.90],
            ['name' => 'Колодки тормозные передние ATE 24.0136-1123.2', 'sku' => 'ATE-24.0136', 'category' => 'brake-pads', 'brand' => 'ate', 'price' => 52.00],
            ['name' => 'Диск тормозной передний TRW DF7232', 'sku' => 'TRW-DF7232', 'category' => 'brake-discs', 'brand' => 'trw', 'price' => 68.50],
            ['name' => 'Диск тормозной передний Bosch 0986AB4236', 'sku' => 'BOS-0986AB4236', 'category' => 'brake-discs', 'brand' => 'bosch', 'price' => 71.20],
            ['name' => 'Суппорт тормозной передний TRW BHS1024E', 'sku' => 'TRW-BHS1024E', 'category' => 'brake-calipers', 'brand' => 'trw', 'price' => 126.00],
            ['name' => 'Суппорт тормозной задний Bosch 0986473002', 'sku' => 'BOS-0986473002', 'category' => 'brake-calipers', 'brand' => 'bosch', 'price' => 118.40],
            ['name' => 'Амортизатор передний Sachs 314 821', 'sku' => 'SAC-314821', 'category' => 'shock-absorbers', 'brand' => 'sachs', 'price' => 89.00],
            ['name' => 'Амортизатор задний Sachs 314 822', 'sku' => 'SAC-314822', 'category' => 'shock-absorbers', 'brand' => 'sachs', 'price' => 62.00],
            ['name' => 'Стойка стабилизатора Lemforder 25670 01', 'sku' => 'LEM-2567001', 'category' => 'stabilizer-links', 'brand' => 'lemforder', 'price' => 18.90],
            ['name' => 'Шаровая опора Lemforder 35670 01', 'sku' => 'LEM-3567001', 'category' => 'ball-joints', 'brand' => 'lemforder', 'price' => 34.50],
            ['name' => 'Свечи зажигания NGK BCPR6ES', 'sku' => 'NGK-BCPR6ES', 'category' => 'spark-plugs', 'brand' => 'ngk', 'price' => 4.20],
            ['name' => 'Свечи зажигания Denso PK20PR-P8', 'sku' => 'DEN-PK20PR-P8', 'category' => 'spark-plugs', 'brand' => 'denso', 'price' => 5.80],
            ['name' => 'Фильтр воздушный Bosch 0986AF3027', 'sku' => 'BOS-AF3027', 'category' => 'filters', 'brand' => 'bosch', 'price' => 12.90],
            ['name' => 'Фильтр масляный Valeo 025170', 'sku' => 'VAL-025170', 'category' => 'filters', 'brand' => 'valeo', 'price' => 8.50],
            ['name' => 'Комплект ремня ГРМ Bosch 1987949668', 'sku' => 'BOS-1987949668', 'category' => 'timing-belts', 'brand' => 'bosch', 'price' => 94.00],
            ['name' => 'Комплект ремня ГРМ ATE K015670XS', 'sku' => 'ATE-K015670XS', 'category' => 'timing-belts', 'brand' => 'ate', 'price' => 108.90],
            ['name' => 'Аккумулятор Bosch S4 74Ah 680A', 'sku' => 'BOS-S474AH', 'category' => 'batteries', 'brand' => 'bosch', 'price' => 129.00],
            ['name' => 'Аккумулятор Valeo Start-Stop 70Ah 760A', 'sku' => 'VAL-SS70AH', 'category' => 'batteries', 'brand' => 'valeo', 'price' => 142.50],
            ['name' => 'Стартер Valeo 438185', 'sku' => 'VAL-438185', 'category' => 'starters-generators', 'brand' => 'valeo', 'price' => 176.00],
            ['name' => 'Генератор Bosch 0121715001', 'sku' => 'BOS-0121715001', 'category' => 'starters-generators', 'brand' => 'bosch', 'price' => 214.90],
        ];

        foreach ($products as $p) {
            $category = $categories->get($p['category']);
            $brand = $brands->get($p['brand']);
            if (!$category) {
                continue;
            }

            $slug = Str::slug($p['name']);
            $product = Product::updateOrCreate(
                ['sku' => $p['sku']],
                [
                    'category_id' => $category->id,
                    'brand_id' => $brand?->id,
                    'name' => $p['name'],
                    'slug' => $slug . '-' . substr(md5($p['sku']), 0, 6),
                    'short_description' => null,
                    'description' => null,
                    'price' => $p['price'],
                    'is_active' => true,
                    'type' => 'part',
                ]
            );

            Stock::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => rand(5, 100),
                    'reserved_quantity' => 0,
                ]
            );
        }
    }
}
