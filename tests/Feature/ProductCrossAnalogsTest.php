<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCrossNumber;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrossAnalogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_analog_skipped_when_linked_product_has_no_shared_vehicle(): void
    {
        $category = Category::create([
            'name' => 'Parts',
            'slug' => 'parts',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BMW',
            'slug' => 'bmw-cross-test',
            'is_active' => true,
        ]);

        $vehicleArm = Vehicle::create([
            'make' => 'Bmw',
            'model' => '3 (E90)',
            'generation' => null,
            'year_from' => 2005,
            'year_to' => 2012,
            'engine' => null,
            'body_type' => null,
        ]);

        $vehicleOther = Vehicle::create([
            'make' => 'Audi',
            'model' => 'A4',
            'generation' => null,
            'year_from' => 2008,
            'year_to' => 2015,
            'engine' => null,
            'body_type' => null,
        ]);

        $arm = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => '31126775972',
            'name' => 'Рычаг тест',
            'slug' => 'rychag-test',
            'price' => 100,
            'is_active' => true,
            'type' => 'part',
        ]);
        $arm->vehicles()->attach($vehicleArm->id);

        $bushing = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => '3538701',
            'name' => 'Сайлентблок тест',
            'slug' => 'silent-test',
            'price' => 50,
            'is_active' => true,
            'type' => 'part',
        ]);
        $bushing->vehicles()->attach($vehicleOther->id);

        ProductCrossNumber::create([
            'product_id' => $arm->id,
            'cross_number' => '35387 01',
            'manufacturer_name' => 'Lemforder',
        ]);

        $arm->load(['crossNumbers', 'vehicles']);

        $this->assertCount(0, $arm->crossNumbersWithLinkedProducts());
    }

    public function test_cross_analog_included_when_at_least_one_vehicle_matches(): void
    {
        $category = Category::create([
            'name' => 'Parts',
            'slug' => 'parts-2',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Lemforder',
            'slug' => 'lemforder-cross-test',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'make' => 'Bmw',
            'model' => '3 (F30)',
            'generation' => null,
            'year_from' => 2012,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);

        $main = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'MAIN-001',
            'name' => 'Деталь основная',
            'slug' => 'main-001',
            'price' => 200,
            'is_active' => true,
            'type' => 'part',
        ]);
        $main->vehicles()->attach($vehicle->id);

        $other = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => '3621501',
            'name' => 'Деталь аналог',
            'slug' => 'other-36215',
            'price' => 180,
            'is_active' => true,
            'type' => 'part',
        ]);
        $other->vehicles()->attach($vehicle->id);

        ProductCrossNumber::create([
            'product_id' => $main->id,
            'cross_number' => '36215 01',
            'manufacturer_name' => 'Lemforder',
        ]);

        $main->load(['crossNumbers', 'vehicles']);

        $rows = $main->crossNumbersWithLinkedProducts();
        $this->assertCount(1, $rows);
        $this->assertSame($other->id, $rows->first()->linked->id);
    }

    public function test_cross_analog_without_vehicle_filter_when_main_has_no_vehicles(): void
    {
        $category = Category::create([
            'name' => 'Parts',
            'slug' => 'parts-3',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Generic',
            'slug' => 'generic-cross',
            'is_active' => true,
        ]);

        $main = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'NO-VIN-1',
            'name' => 'Без применяемости',
            'slug' => 'no-vin-1',
            'price' => 10,
            'is_active' => true,
            'type' => 'part',
        ]);

        $linked = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'LINK-999',
            'name' => 'Связанный',
            'slug' => 'link-999',
            'price' => 11,
            'is_active' => true,
            'type' => 'part',
        ]);

        ProductCrossNumber::create([
            'product_id' => $main->id,
            'cross_number' => 'LINK-999',
            'manufacturer_name' => null,
        ]);

        $main->load(['crossNumbers', 'vehicles']);

        $this->assertCount(1, $main->crossNumbersWithLinkedProducts());
    }
}
