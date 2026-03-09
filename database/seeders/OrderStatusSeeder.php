<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Новый', 'slug' => 'new', 'color' => '#3b82f6', 'sort' => 10],
            ['name' => 'Подтверждён', 'slug' => 'confirmed', 'color' => '#8b5cf6', 'sort' => 20],
            ['name' => 'В обработке', 'slug' => 'processing', 'color' => '#f59e0b', 'sort' => 30],
            ['name' => 'Отправлен', 'slug' => 'shipped', 'color' => '#06b6d4', 'sort' => 40],
            ['name' => 'Доставлен', 'slug' => 'delivered', 'color' => '#22c55e', 'sort' => 50],
            ['name' => 'Отменён', 'slug' => 'cancelled', 'color' => '#ef4444', 'sort' => 60],
        ];

        foreach ($statuses as $status) {
            OrderStatus::updateOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
}
