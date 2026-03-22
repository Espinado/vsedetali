<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OrderStatusSeeder::class,
            ShippingMethodSeeder::class,
            PaymentMethodSeeder::class,
            SettingSeeder::class,
            WarehouseSeeder::class,
            GeelyBambooCatalogSeeder::class,
            PageSeeder::class,
            BannerSeeder::class,
        ]);
    }
}
