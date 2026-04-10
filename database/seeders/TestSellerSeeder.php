<?php

namespace Database\Seeders;

use App\Models\Seller;
use App\Models\SellerStaff;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Тестовый продавец для локальной проверки кабинета /seller (без письма с приглашением).
 */
class TestSellerSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(SellerStaffRoleSeeder::class);

        $seller = Seller::query()->updateOrCreate(
            ['slug' => 'test-seller'],
            [
                'user_id' => null,
                'name' => 'Тестовый продавец',
                'contract_date' => now()->toDateString(),
                'commission_percent' => 10,
                'status' => 'active',
            ]
        );

        $code = 'SELLER-TEST-'.$seller->id;
        Warehouse::query()->updateOrCreate(
            ['code' => $code],
            [
                'seller_id' => $seller->id,
                'name' => 'Склад тестового продавца',
                'is_default' => false,
                'is_active' => true,
            ]
        );

        $email = 'seller-demo@vsedetali.test';
        $staff = SellerStaff::query()->firstOrNew(['email' => $email]);
        $staff->seller_id = $seller->id;
        $staff->name = 'Демо Админ';
        $staff->password = 'password';
        $staff->invite_token_hash = null;
        $staff->invite_expires_at = null;
        $staff->save();

        if ($staff->roles()->doesntExist()) {
            $staff->assignRole('admin');
        }

        $this->command?->info('Тестовый продавец: '.$seller->name.' (slug: test-seller). Вход /seller — '.$email.' / password');
    }
}
