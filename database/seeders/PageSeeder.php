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
                'body' => '<p>Режим работы и прочие уточнения можно добавить ниже в «Доп. текст».</p>',
                'contact_email' => 'info@vsedetalki.ru',
                'contact_phone' => '+370 XXX XXXXX',
                'contact_address' => 'г. Вильнюс, ул. Примерная, 1',
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
