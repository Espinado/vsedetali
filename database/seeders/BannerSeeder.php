<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Главный баннер',
                'image' => 'banners/welcome.jpg',
                'link' => '/catalog',
                'sort' => 10,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
            ]
        );
    }
}
