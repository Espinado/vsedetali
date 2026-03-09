<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Bosch', 'slug' => 'bosch'],
            ['name' => 'ATE', 'slug' => 'ate'],
            ['name' => 'Sachs', 'slug' => 'sachs'],
            ['name' => 'TRW', 'slug' => 'trw'],
            ['name' => 'Lemforder', 'slug' => 'lemforder'],
            ['name' => 'NGK', 'slug' => 'ngk'],
            ['name' => 'Denso', 'slug' => 'denso'],
            ['name' => 'Valeo', 'slug' => 'valeo'],
        ];

        foreach ($brands as $b) {
            Brand::updateOrCreate(
                ['slug' => $b['slug']],
                array_merge($b, ['is_active' => true])
            );
        }
    }
}
