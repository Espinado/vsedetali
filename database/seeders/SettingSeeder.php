<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'store_name', 'value' => 'vsedetalki.ru', 'group' => 'general'],
            ['key' => 'store_email', 'value' => 'info@vsedetalki.ru', 'group' => 'general'],
            ['key' => 'store_phone', 'value' => '', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'RUB', 'group' => 'general'],
            ['key' => 'orders_notify_email', 'value' => 'jevgen@vsedetalki.ru', 'group' => 'orders'],
            ['key' => 'site_meta_description', 'value' => 'Интернет-магазин автозапчастей. Каталог, доставка, удобная оплата.', 'group' => 'general'],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}
