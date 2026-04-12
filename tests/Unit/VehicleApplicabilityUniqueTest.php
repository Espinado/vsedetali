<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleApplicabilityUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicability_duplicate_exists(): void
    {
        Vehicle::create([
            'make' => 'Opel',
            'model' => 'Vectra',
            'generation' => null,
            'year_from' => 2015,
            'year_to' => 2015,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->assertTrue(Vehicle::applicabilityDuplicateExists('Opel', 'Vectra', null, 2015, 2015, null));
        $this->assertFalse(Vehicle::applicabilityDuplicateExists('Opel', 'Vectra', null, 2016, 2019, null));
    }

    public function test_assert_applicability_unique_or_throw_on_duplicate(): void
    {
        Vehicle::create([
            'make' => 'Opel',
            'model' => 'Vectra',
            'generation' => 'Rest',
            'year_from' => 2016,
            'year_to' => 2019,
            'engine' => null,
            'body_type' => null,
        ]);

        $this->expectException(ValidationException::class);

        Vehicle::assertApplicabilityUniqueOrThrow([
            'make' => 'Opel',
            'model' => 'Vectra',
            'generation' => 'Rest',
            'year_from' => 2016,
            'year_to' => 2019,
        ], null);
    }

    public function test_assert_applicability_unique_allows_same_record_on_edit(): void
    {
        $v = Vehicle::create([
            'make' => 'Opel',
            'model' => 'Astra',
            'generation' => null,
            'year_from' => 2010,
            'year_to' => 2015,
            'engine' => null,
            'body_type' => null,
        ]);

        Vehicle::assertApplicabilityUniqueOrThrow([
            'make' => 'Opel',
            'model' => 'Astra',
            'generation' => null,
            'year_from' => 2010,
            'year_to' => 2015,
        ], $v->id);

        $this->assertTrue(true);
    }
}
