<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Support\SellerListingVehicleCompatibilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SellerListingVehicleCompatibilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_years_accepts_list_range_hyphen_and_en_dash(): void
    {
        $this->assertSame([2018, 2019, 2020], SellerListingVehicleCompatibilities::parseYears('2018, 2019, 2020'));
        $this->assertSame([2015, 2016, 2017], SellerListingVehicleCompatibilities::parseYears('2015-2017'));
        $this->assertSame([2015, 2016, 2017], SellerListingVehicleCompatibilities::parseYears('2015–2017'));
    }

    public function test_parse_years_accepts_spaces_around_hyphen_and_unicode_minus(): void
    {
        $this->assertSame(
            [2015, 2016, 2017, 2018, 2019, 2020],
            SellerListingVehicleCompatibilities::parseYears('2015 - 2020')
        );
        $minus = "\u{2212}";
        $this->assertSame(
            [2016, 2017, 2018],
            SellerListingVehicleCompatibilities::parseYears('2016'.$minus.'2018')
        );
    }

    public function test_normalize_repeater_rejects_year_range_wider_than_allowed(): void
    {
        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::normalizeRepeaterRows([
            [
                'vehicle_make' => 'A',
                'vehicle_model' => 'B',
                'compatibility_years' => '2000-2062',
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_normalize_repeater_rejects_generation_text_in_manual_years_field(): void
    {
        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::normalizeRepeaterRows([
            [
                'vehicle_make' => 'A',
                'vehicle_model' => 'B',
                'compatibility_years' => '2015-2020 F30',
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_normalize_repeater_rejects_too_many_distinct_years_in_one_string(): void
    {
        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::normalizeRepeaterRows([
            [
                'vehicle_make' => 'A',
                'vehicle_model' => 'B',
                'compatibility_years' => '1950-2010, 2011-2071',
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_collect_vehicle_ids_matches_catalog_year_ranges(): void
    {
        $v = Vehicle::create([
            'make' => 'TestMake',
            'model' => 'TestModel',
            'generation' => null,
            'year_from' => 2018,
            'year_to' => 2022,
            'engine' => null,
            'body_type' => null,
        ]);

        $ids =         SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'TestMake',
                'vehicle_model' => 'TestModel',
                'compatibility_years' => [2019],
                'vehicle_row_ids' => [],
            ],
        ]);

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($v->id));
    }

    public function test_collect_vehicle_ids_rejects_years_outside_catalog_ranges(): void
    {
        Vehicle::create([
            'make' => 'TestMake',
            'model' => 'TestModel',
            'generation' => null,
            'year_from' => 2018,
            'year_to' => 2022,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'TestMake',
                'vehicle_model' => 'TestModel',
                'compatibility_years' => [2010],
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_normalize_repeater_rejects_invalid_token_in_year_string(): void
    {
        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::normalizeRepeaterRows([
            [
                'vehicle_make' => 'A',
                'vehicle_model' => 'B',
                'compatibility_years' => '2015, foo',
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_normalize_repeater_rejects_year_not_in_catalog_subset(): void
    {
        Vehicle::create([
            'make' => 'Nm',
            'model' => 'Mdl',
            'generation' => null,
            'year_from' => 2015,
            'year_to' => 2016,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::normalizeRepeaterRows([
            [
                'vehicle_make' => 'Nm',
                'vehicle_model' => 'Mdl',
                'compatibility_years' => [2020],
                'vehicle_row_ids' => [],
            ],
        ]);
    }

    public function test_collect_vehicle_ids_accepts_catalog_row_ids_with_applicability_years(): void
    {
        $v = Vehicle::create([
            'make' => 'PickMake',
            'model' => 'PickModel',
            'generation' => 'B',
            'year_from' => 2016,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);

        $ids = SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'PickMake',
                'vehicle_model' => 'PickModel',
                'compatibility_years' => [2018],
                'vehicle_row_ids' => [$v->id],
            ],
        ]);

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($v->id));
    }

    public function test_collect_vehicle_ids_ignores_row_picks_when_catalog_has_no_year_ranges(): void
    {
        $v = Vehicle::create([
            'make' => 'NoYearMake',
            'model' => 'NoYearModel',
            'generation' => null,
            'year_from' => null,
            'year_to' => null,
            'engine' => null,
            'body_type' => null,
        ]);

        $ids = SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'NoYearMake',
                'vehicle_model' => 'NoYearModel',
                'compatibility_years' => [2020],
                'vehicle_row_ids' => [$v->id],
            ],
        ]);

        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($v->id));
    }

    public function test_collect_vehicle_ids_rejects_catalog_picks_without_applicability_years(): void
    {
        $v = Vehicle::create([
            'make' => 'YMake',
            'model' => 'YModel',
            'generation' => null,
            'year_from' => 2020,
            'year_to' => 2022,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'YMake',
                'vehicle_model' => 'YModel',
                'compatibility_years' => [],
                'vehicle_row_ids' => [$v->id],
            ],
        ]);
    }

    public function test_collect_vehicle_ids_rejects_picks_only_when_catalog_has_no_year_ranges(): void
    {
        $v = Vehicle::create([
            'make' => 'SoloMake',
            'model' => 'SoloModel',
            'generation' => null,
            'year_from' => null,
            'year_to' => null,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'SoloMake',
                'vehicle_model' => 'SoloModel',
                'compatibility_years' => [],
                'vehicle_row_ids' => [$v->id],
            ],
        ]);
    }

    public function test_collect_vehicle_ids_rejects_row_ids_for_other_make(): void
    {
        $v = Vehicle::create([
            'make' => 'OtherMake',
            'model' => 'PickModel',
            'generation' => null,
            'year_from' => 2015,
            'year_to' => 2015,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::collectVehicleIds([
            [
                'vehicle_make' => 'PickMake',
                'vehicle_model' => 'PickModel',
                'compatibility_years' => [],
                'vehicle_row_ids' => [$v->id],
            ],
        ]);
    }

    public function test_assert_seller_rows_have_pick_or_years(): void
    {
        $this->expectException(ValidationException::class);

        SellerListingVehicleCompatibilities::assertSellerRowsHavePickOrYears([
            [
                'vehicle_make' => 'A',
                'vehicle_model' => 'B',
                'compatibility_years' => [],
                'vehicle_row_ids' => [],
            ],
        ]);
    }
}
