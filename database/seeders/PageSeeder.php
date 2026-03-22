<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'delivery',
                'title' => 'Доставка',
                'body' => '<p>Информация о способах и сроках доставки.</p><p>Курьером по региону — 1–3 дня. Самовывоз со склада — бесплатно.</p>',
            ],
            [
                'slug' => 'payment',
                'title' => 'Оплата',
                'body' => '<p>Мы принимаем оплату банковскими картами, переводом на расчётный счёт и наличными при получении.</p>',
            ],
            [
                'slug' => 'contacts',
                'title' => 'Контакты',
                'body' => '<p>Email: info@vsedetalki.ru</p><p>Телефон: +370 XXX XXXXX</p><p>Адрес: г. Вильнюс, ул. Примерная, 1</p>',
            ],
        ];

        foreach ($pages as $data) {
            Page::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
