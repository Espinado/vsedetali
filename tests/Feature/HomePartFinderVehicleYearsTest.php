<?php

namespace Tests\Feature;

use App\Livewire\Storefront\HomePartFinder;
use App\Models\Brand;
use App\Support\ProductNameVehicleExtractor;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomePartFinderVehicleYearsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Livewire::withQueryParams([]);
        ProductNameVehicleExtractor::clearMakesCache();
        parent::tearDown();
    }

    public function test_categories_include_product_when_pivot_years_narrower_than_vehicle_row(): void
    {
        $category = Category::create([
            'name' => 'CatHf',
            'slug' => 'cat-hf-year',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandHf',
            'slug' => 'brand-hf-year',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Seat',
            'model' => 'Leon',
            'generation' => null,
            'year_from' => 2012,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-YEAR-001',
            'name' => 'Деталь Leon только 2015',
            'slug' => 'test-hf-year-001',
            'price' => 11,
            'is_active' => true,
            'type' => 'part',
        ]);
        $product->vehicles()->attach($vehicle->id, [
            'compat_year_from' => 2015,
            'compat_year_to' => 2015,
        ]);

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Seat')
            ->set('vehicleId', $vehicle->id)
            ->assertSet('categoryId', 0)
            ->assertSee('value="'.$vehicle->id.'"', false)
            ->set('categoryId', $category->id)
            ->assertSee('HF-YEAR-001', false);
    }

    public function test_variant_select_shows_year_range_in_one_option_for_vehicle_row(): void
    {
        $category = Category::create([
            'name' => 'CatHf2',
            'slug' => 'cat-hf-year2',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandHf2',
            'slug' => 'brand-hf-year2',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Skoda',
            'model' => 'Fabia',
            'generation' => null,
            'year_from' => 2016,
            'year_to' => 2018,
            'engine' => null,
            'body_type' => null,
        ]);
        foreach ([2016, 2017, 2018] as $i => $y) {
            $p = Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'sku' => 'HF-RANGE-'.$y,
                'name' => 'Деталь Fabia '.$y,
                'slug' => 'test-hf-range-'.$y,
                'price' => 10 + $i,
                'is_active' => true,
                'type' => 'part',
            ]);
            $p->vehicles()->attach($vehicle->id);
        }

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Skoda')
            ->assertSee('>Fabia (2016–2018)<', false)
            ->assertSee('value="'.$vehicle->id.'"', false);
    }

    public function test_legacy_query_vehicle_year_resolves_vehicle_id(): void
    {
        $category = Category::create([
            'name' => 'CatLegacy',
            'slug' => 'cat-legacy',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandLegacy',
            'slug' => 'brand-legacy',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Audi',
            'model' => 'A3',
            'generation' => '8P',
            'year_from' => 2005,
            'year_to' => 2012,
            'engine' => '1.6',
            'body_type' => 'Хэтчбек',
        ]);
        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'LEG-001',
            'name' => 'Фильтр A3',
            'slug' => 'test-legacy-001',
            'price' => 5,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vehicle->id);

        Livewire::withQueryParams([
            'vehicleMake' => 'Audi',
            'vehicleModel' => 'A3',
            'vehicleYear' => '2010',
        ])->test(HomePartFinder::class)
            ->assertSet('vehicleId', $vehicle->id);
    }

    public function test_legacy_query_without_year_does_not_resolve_when_multiple_variants(): void
    {
        $category = Category::create([
            'name' => 'CatAmb',
            'slug' => 'cat-amb',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandAmb',
            'slug' => 'brand-amb',
            'is_active' => true,
        ]);
        $v1 = Vehicle::create([
            'make' => 'VW',
            'model' => 'Golf',
            'generation' => 'Mk6',
            'year_from' => 2009,
            'year_to' => 2012,
            'engine' => null,
            'body_type' => null,
        ]);
        $v2 = Vehicle::create([
            'make' => 'VW',
            'model' => 'Golf',
            'generation' => 'Mk7',
            'year_from' => 2013,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);
        foreach ([$v1, $v2] as $v) {
            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'sku' => 'AMB-'.$v->id,
                'name' => 'Часть Golf',
                'slug' => 'test-amb-'.$v->id,
                'price' => 1,
                'is_active' => true,
                'type' => 'part',
            ])->vehicles()->attach($v->id);
        }

        Livewire::withQueryParams([
            'vehicleMake' => 'VW',
            'vehicleModel' => 'Golf',
        ])->test(HomePartFinder::class)
            ->assertSet('vehicleId', 0);
    }

    public function test_vehicle_variant_hidden_when_only_name_conflicting_products(): void
    {
        $cat = Category::create([
            'name' => 'CatHideVar',
            'slug' => 'cat-hide-var',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandHideVar',
            'slug' => 'brand-hide-var',
            'is_active' => true,
        ]);
        $vGeely = Vehicle::create([
            'make' => 'Geely',
            'model' => 'Tugella',
            'generation' => null,
            'year_from' => 2020,
            'year_to' => 2024,
            'engine' => null,
            'body_type' => null,
        ]);
        Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HIDE-VAR-GEELY-ANCHOR',
            'name' => 'Якорь Geely Tugella',
            'slug' => 'test-hide-var-geely-anchor',
            'price' => 1,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vGeely->id);
        ProductNameVehicleExtractor::clearMakesCache();

        $vLadaOnlyBad = Vehicle::create([
            'make' => 'Lada',
            'model' => 'OnlyBad',
            'generation' => null,
            'year_from' => 2000,
            'year_to' => 2010,
            'engine' => null,
            'body_type' => null,
        ]);
        Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HIDE-VAR-BAD',
            'name' => 'Деталь Geely Tugella для теста',
            'slug' => 'test-hide-var-bad',
            'price' => 1,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vLadaOnlyBad->id);

        $vLadaOk = Vehicle::create([
            'make' => 'Lada',
            'model' => 'OkSamara',
            'generation' => null,
            'year_from' => 2000,
            'year_to' => 2010,
            'engine' => null,
            'body_type' => null,
        ]);
        Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HIDE-VAR-OK',
            'name' => 'Фильтр Lada Samara',
            'slug' => 'test-hide-var-ok',
            'price' => 2,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vLadaOk->id);

        ProductNameVehicleExtractor::clearMakesCache();

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Lada')
            ->assertDontSee('value="'.$vLadaOnlyBad->id.'"', false)
            ->assertSee('value="'.$vLadaOk->id.'"', false);
    }

    public function test_parts_hide_products_whose_name_leads_with_foreign_make(): void
    {
        $cat = Category::create([
            'name' => 'Стартер HF',
            'slug' => 'cat-hf-starter',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandGeely',
            'slug' => 'brand-geely',
            'is_active' => true,
        ]);
        $vehicleGeely = Vehicle::create([
            'make' => 'Geely',
            'model' => 'Coolray',
            'generation' => null,
            'year_from' => 2019,
            'year_to' => 2024,
            'engine' => null,
            'body_type' => null,
        ]);
        Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-GEELY-ANCHOR',
            'name' => 'Заглушка Geely Coolray',
            'slug' => 'test-hf-geely-anchor',
            'price' => 1,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vehicleGeely->id);

        ProductNameVehicleExtractor::clearMakesCache();

        $vehicleLada = Vehicle::create([
            'make' => 'Lada',
            'model' => 'Samara',
            'generation' => null,
            'year_from' => 1990,
            'year_to' => 2010,
            'engine' => null,
            'body_type' => null,
        ]);
        $wrong = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-GEELY-OPORA',
            'name' => 'Опора амортизатора 4013057900 Geely Tugella',
            'slug' => 'test-hf-geely-opora',
            'price' => 99,
            'is_active' => true,
            'type' => 'part',
        ]);
        $wrong->vehicles()->attach($vehicleLada->id);
        $ok = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-LADA-START',
            'name' => 'Стартер Lada Samara',
            'slug' => 'test-hf-lada-start',
            'price' => 50,
            'is_active' => true,
            'type' => 'part',
        ]);
        $ok->vehicles()->attach($vehicleLada->id);

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Lada')
            ->set('vehicleId', $vehicleLada->id)
            ->set('categoryId', $cat->id)
            ->assertSee('HF-LADA-START', false)
            ->assertDontSee('HF-GEELY-OPORA', false);
    }

    public function test_parts_hide_exeed_in_name_even_without_exeed_vehicle_make_row(): void
    {
        $cat = Category::create([
            'name' => 'Топливный фильтр HF',
            'slug' => 'cat-hf-fuel-filter',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandPeugeotHf',
            'slug' => 'brand-peugeot-hf',
            'is_active' => true,
        ]);
        $vehiclePeugeot = Vehicle::create([
            'make' => 'Peugeot',
            'model' => '205',
            'generation' => null,
            'year_from' => 1983,
            'year_to' => 1990,
            'engine' => null,
            'body_type' => null,
        ]);
        Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-PG-ANCHOR',
            'name' => 'Топливный фильтр Peugeot 205',
            'slug' => 'test-hf-pg-anchor',
            'price' => 1,
            'is_active' => true,
            'type' => 'part',
        ])->vehicles()->attach($vehiclePeugeot->id);

        $wrong = Product::create([
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-EXEED-DEFLECTOR',
            'name' => 'Дефлектор вентиляции правый 302000395AA Exeed LX',
            'slug' => 'test-hf-exeed-deflector',
            'price' => 99,
            'is_active' => true,
            'type' => 'part',
        ]);
        $wrong->vehicles()->attach($vehiclePeugeot->id);

        ProductNameVehicleExtractor::clearMakesCache();

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Peugeot')
            ->set('vehicleId', $vehiclePeugeot->id)
            ->set('categoryId', $cat->id)
            ->assertSee('HF-PG-ANCHOR', false)
            ->assertDontSee('HF-EXEED-DEFLECTOR', false);
    }

    public function test_parts_list_respects_category_and_vehicle(): void
    {
        $catSensors = Category::create([
            'name' => 'Датчики HF',
            'slug' => 'cat-hf-sensors',
            'is_active' => true,
        ]);
        $catPaint = Category::create([
            'name' => 'Краски HF',
            'slug' => 'cat-hf-paint',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'BrandIso',
            'slug' => 'brand-iso',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'make' => 'Lada',
            'model' => 'Samara',
            'generation' => null,
            'year_from' => 1990,
            'year_to' => 2010,
            'engine' => null,
            'body_type' => null,
        ]);
        $pSensor = Product::create([
            'category_id' => $catSensors->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-SENSOR-1',
            'name' => 'Датчик ABS Lada',
            'slug' => 'test-hf-sensor-1',
            'price' => 10,
            'is_active' => true,
            'type' => 'part',
        ]);
        $pSensor->vehicles()->attach($vehicle->id);
        $pPaint = Product::create([
            'category_id' => $catPaint->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-PAINT-1',
            'name' => 'Лак универсальный',
            'slug' => 'test-hf-paint-1',
            'price' => 20,
            'is_active' => true,
            'type' => 'part',
        ]);
        $pPaint->vehicles()->attach($vehicle->id);
        $pWrongCat = Product::create([
            'category_id' => $catSensors->id,
            'brand_id' => $brand->id,
            'sku' => 'HF-OTHER-CAR',
            'name' => 'Датчик для Chery',
            'slug' => 'test-hf-chery',
            'price' => 30,
            'is_active' => true,
            'type' => 'part',
        ]);
        $otherVehicle = Vehicle::create([
            'make' => 'Chery',
            'model' => 'Tiggo',
            'generation' => null,
            'year_from' => 2015,
            'year_to' => 2020,
            'engine' => null,
            'body_type' => null,
        ]);
        $pWrongCat->vehicles()->attach($otherVehicle->id);

        Livewire::test(HomePartFinder::class)
            ->set('vehicleMake', 'Lada')
            ->set('vehicleId', $vehicle->id)
            ->set('categoryId', $catSensors->id)
            ->assertSee('HF-SENSOR-1', false)
            ->assertDontSee('HF-OTHER-CAR', false)
            ->assertDontSee('HF-PAINT-1', false)
            ->set('categoryId', $catPaint->id)
            ->assertSee('HF-PAINT-1', false)
            ->assertDontSee('HF-SENSOR-1', false);
    }
}
