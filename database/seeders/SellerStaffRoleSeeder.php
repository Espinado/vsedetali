<?php

namespace Database\Seeders;

use App\Authorization\StaffPermission;
use App\Models\SellerStaff;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Роли и права персонала продавца: те же имена ролей и те же строки разрешений, что у {@see RolePermissionSeeder},
 * но guard {@see SellerStaff::$guard_name} — в Spatie это отдельный набор записей в БД.
 */
class SellerStaffRoleSeeder extends Seeder
{
    public const GUARD = 'seller_staff';

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

        // Как у площадки, плюс каталог/склад: в /seller это карточки на маркетплейсе и привязка к складам продавца.
        $manager->syncPermissions([
            StaffPermission::CATALOG_MANAGE,
            StaffPermission::WAREHOUSE_MANAGE,
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
            StaffPermission::CATALOG_MANAGE,
            StaffPermission::ORDERS_VIEW,
            StaffPermission::SHIPMENTS_MANAGE,
        ]);

        SellerStaff::query()->each(function (SellerStaff $staff): void {
            if ($staff->roles()->doesntExist()) {
                $staff->assignRole('admin');
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
