<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'Курьером', 'description' => 'Доставка курьером по адресу', 'cost' => 5.00, 'free_from' => 100, 'sort' => 10],
            ['name' => 'Почтой', 'description' => 'Доставка почтой', 'cost' => 3.50, 'free_from' => 150, 'sort' => 20],
            ['name' => 'Самовывоз', 'description' => 'Самовывоз со склада', 'cost' => 0, 'free_from' => null, 'sort' => 30],
        ];

        foreach ($methods as $method) {
            ShippingMethod::updateOrCreate(
                ['name' => $method['name']],
                $method
            );
        }
    }
}
