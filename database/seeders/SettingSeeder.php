<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'store_name', 'value' => 'vsedetalki', 'group' => 'general'],
            ['key' => 'store_email', 'value' => 'info@vsedetalki.ru', 'group' => 'general'],
            ['key' => 'store_phone', 'value' => '', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'EUR', 'group' => 'general'],
            ['key' => 'orders_notify_email', 'value' => 'orders@vsedetalki.ru', 'group' => 'orders'],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}
