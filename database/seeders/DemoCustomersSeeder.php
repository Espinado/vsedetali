<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Простые тестовые покупатели (вход в магазин: email + пароль «password»).
 */
class DemoCustomersSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name' => 'Иван Петров',
                'email' => 'ivan@demo.local',
                'phone' => '+7 900 111-22-33',
            ],
            [
                'name' => 'Мария Сидорова',
                'email' => 'maria@demo.local',
                'phone' => '+7 900 444-55-66',
            ],
        ];

        foreach ($customers as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'phone' => $row['phone'],
                    'password' => 'password',
                ]
            );
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
