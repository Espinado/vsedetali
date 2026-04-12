<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use PHPUnit\Framework\TestCase;

class VehicleStorefrontYearSuffixTest extends TestCase
{
    public function test_suffix_single_year_when_from_equals_to(): void
    {
        $v = new Vehicle([
            'year_from' => 2010,
            'year_to' => 2010,
        ]);

        $this->assertSame(' (2010)', $v->storefrontYearRangeSuffix());
    }

    public function test_suffix_range(): void
    {
        $v = new Vehicle([
            'year_from' => 2007,
            'year_to' => 2011,
        ]);

        $this->assertSame(' (2007–2011)', $v->storefrontYearRangeSuffix());
    }

    public function test_suffix_when_years_missing(): void
    {
        $v = new Vehicle([
            'year_from' => null,
            'year_to' => null,
        ]);

        $this->assertSame('', $v->storefrontYearRangeSuffix());
    }

    public function test_suffix_open_start(): void
    {
        $v = new Vehicle([
            'year_from' => null,
            'year_to' => 2015,
        ]);

        $this->assertSame(' (до 2015)', $v->storefrontYearRangeSuffix());
    }

    public function test_suffix_open_end(): void
    {
        $v = new Vehicle([
            'year_from' => 2020,
            'year_to' => null,
        ]);

        $this->assertSame(' (с 2020)', $v->storefrontYearRangeSuffix());
    }
}
