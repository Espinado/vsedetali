<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiclePartsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_vehicle_id_query_to_vehicle_parts_page(): void
    {
        $category = Category::create([
            'name' => 'Cat',
            'slug' => 'cat-vp',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Brand',
            'slug' => 'brand-vp',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Bmw',
            'model' => '3 (E90)',
            'generation' => null,
            'year_from' => 2005,
            'year_to' => 2012,
            'engine' => null,
            'body_type' => null,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'VP-001',
            'name' => 'Тестовая деталь для авто',
            'slug' => 'test-vp-001',
            'price' => 99,
            'is_active' => true,
            'type' => 'part',
        ]);
        $product->vehicles()->attach($vehicle->id);

        $this->get('/?vehicleId='.$vehicle->id)
            ->assertRedirect(route('vehicle.parts', $vehicle));

        $this->get(route('vehicle.parts', $vehicle))
            ->assertOk()
            ->assertSee('Тестовая деталь для авто', false)
            ->assertSee('Запчасти для', false)
            ->assertSee('2005', false)
            ->assertSee('2012', false);
    }

    public function test_unknown_vehicle_id_on_home_does_not_redirect(): void
    {
        $this->get('/?vehicleId=999999')
            ->assertOk();
    }

    public function test_parts_by_car_query_shows_vehicle_year_in_title_and_products(): void
    {
        $category = Category::create([
            'name' => 'Cat2',
            'slug' => 'cat-vp2',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Brand2',
            'slug' => 'brand-vp2',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Audi',
            'model' => 'A4',
            'generation' => null,
            'year_from' => 2008,
            'year_to' => 2015,
            'engine' => null,
            'body_type' => null,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'VP-002',
            'name' => 'Деталь Audi',
            'slug' => 'test-vp-002',
            'price' => 50,
            'is_active' => true,
            'type' => 'part',
        ]);
        $product->vehicles()->attach($vehicle->id);

        $this->get(route('vehicle.by_car', [
            'vehicleId' => $vehicle->id,
            'vehicleMake' => 'Audi',
            'vehicleModel' => 'A4',
            'vehicleYear' => 2010,
        ]))
            ->assertOk()
            ->assertSee('Деталь Audi', false)
            ->assertSee('2008', false)
            ->assertSee('2015', false);
    }

    /**
     * Без vehicleId витрина фильтрует по марке/модели и году: год должен попадать в year_from–year_to
     * у привязанной записи справочника (как при сохранении товара через collectVehicleIds).
     */
    public function test_parts_by_car_without_vehicle_id_filters_by_year_against_vehicle_ranges(): void
    {
        $category = Category::create([
            'name' => 'CatYear',
            'slug' => 'cat-year-filter',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandYear',
            'slug' => 'brand-year-filter',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Vw',
            'model' => 'Golf Mk7',
            'generation' => null,
            'year_from' => 2012,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'VP-YEAR-001',
            'name' => 'Уникальная деталь Golf годовой фильтр',
            'slug' => 'test-vp-year-001',
            'price' => 10,
            'is_active' => true,
            'type' => 'part',
        ]);
        $product->vehicles()->attach($vehicle->id);

        $base = [
            'vehicleMake' => 'Vw',
            'vehicleModel' => 'Golf Mk7',
        ];

        $this->get(route('vehicle.by_car', array_merge($base, ['vehicleYear' => 2015])))
            ->assertOk()
            ->assertSee('Уникальная деталь Golf годовой фильтр', false);

        $this->get(route('vehicle.by_car', array_merge($base, ['vehicleYear' => 2005])))
            ->assertOk()
            ->assertDontSee('Уникальная деталь Golf годовой фильтр', false);
    }
}
