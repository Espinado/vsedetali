<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name' => 'Банковская карта', 'code' => 'card', 'sort' => 10],
            ['name' => 'Банковский перевод', 'code' => 'bank_transfer', 'sort' => 20],
            ['name' => 'Наличными при получении', 'code' => 'cod', 'sort' => 30],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                array_merge($method, ['config' => null])
            );
        }
    }
}
