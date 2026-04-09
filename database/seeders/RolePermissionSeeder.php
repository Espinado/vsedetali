<?php

namespace Database\Seeders;

use App\Authorization\StaffPermission;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const GUARD = 'staff';

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (StaffPermission::all() as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        $admin = Role::findOrCreate('admin', self::GUARD);
        $manager = Role::findOrCreate('manager', self::GUARD);
        $accountant = Role::findOrCreate('accountant', self::GUARD);
        $warehouse = Role::findOrCreate('warehouse', self::GUARD);

        $admin->syncPermissions(StaffPermission::all());

        $manager->syncPermissions([
            StaffPermission::ORDERS_VIEW,
            StaffPermission::ORDERS_EDIT,
            StaffPermission::SHIPMENTS_MANAGE,
            StaffPermission::CHAT_MANAGE,
            StaffPermission::CUSTOMERS_VIEW,
        ]);

        $accountant->syncPermissions([
            StaffPermission::FINANCE_VIEW,
        ]);

        $warehouse->syncPermissions([
            StaffPermission::ORDERS_VIEW,
            StaffPermission::SHIPMENTS_MANAGE,
        ]);

        Staff::query()->each(function (Staff $staff): void {
            if ($staff->roles()->doesntExist()) {
                $staff->assignRole('admin');
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
