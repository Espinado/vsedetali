<?php

/**
 * Одноразово создаёт пару товаров с общим ТС и кроссом (для проверки блока «Аналоги» на витрине).
 * Повторный запуск только выводит URL существующего демо-товара.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCrossNumber;
use App\Models\Vehicle;
use App\Support\ProductCatalogSlug;

$skuMain = 'DEMO-ANALOG-MAIN-001';
$skuLinked = '99001001';

$existing = Product::query()->where('sku', $skuMain)->first();
if ($existing !== null) {
    echo route('product.show', $existing).PHP_EOL;
    exit(0);
}

$brand = Brand::query()->where('is_active', true)->first() ?? Brand::query()->first();
$category = Category::query()->where('is_active', true)->first() ?? Category::query()->first();
if ($brand === null || $category === null) {
    fwrite(STDERR, "Нужны хотя бы одна категория и один бренд в БД.\n");
    exit(1);
}

$vehicle = Vehicle::query()->firstOrCreate(
    [
        'make' => 'Bmw',
        'model' => '3 (F30)',
        'generation' => null,
    ],
    [
        'year_from' => 2012,
        'year_to' => 2019,
        'engine' => null,
        'body_type' => null,
    ]
);

$main = new Product([
    'category_id' => $category->id,
    'brand_id' => $brand->id,
    'sku' => $skuMain,
    'name' => 'Демо: товар с аналогом в каталоге',
    'price' => 100,
    'is_active' => true,
    'type' => 'part',
]);
$main->slug = ProductCatalogSlug::unique($main->name, $brand->name);
$main->save();
$main->vehicles()->sync([$vehicle->id]);

$linked = new Product([
    'category_id' => $category->id,
    'brand_id' => $brand->id,
    'sku' => $skuLinked,
    'name' => 'Демо: аналог (другой номер в SKU)',
    'price' => 90,
    'is_active' => true,
    'type' => 'part',
]);
$linked->slug = ProductCatalogSlug::unique($linked->name, $brand->name);
$linked->save();
$linked->vehicles()->sync([$vehicle->id]);

ProductCrossNumber::query()->firstOrCreate(
    [
        'product_id' => $main->id,
        'cross_number' => '9900 1001',
    ],
    [
        'manufacturer_name' => 'Demo cross',
    ]
);

echo route('product.show', $main->fresh()).PHP_EOL;
