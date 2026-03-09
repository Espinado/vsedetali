<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';
        $roles = ['customer', 'admin', 'manager', 'content_manager', 'b2b_customer', 'company_admin', 'seller', 'super_admin'];

        foreach ($roles as $name) {
            DB::table('roles')->updateOrInsert(
                ['name' => $name, 'guard_name' => $guard],
                ['name' => $name, 'guard_name' => $guard, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
