<?php

namespace App\Filament\Concerns;

use App\Authorization\StaffPermission;

trait AuthorizesWarehouseResource
{
    use ChecksStaffPermissions;

    public static function canViewAny(): bool
    {
        return static::allow(StaffPermission::WAREHOUSE_MANAGE);
    }

    public static function canCreate(): bool
    {
        return static::allow(StaffPermission::WAREHOUSE_MANAGE);
    }

    public static function canEdit($record): bool
    {
        return static::allow(StaffPermission::WAREHOUSE_MANAGE);
    }

    public static function canDelete($record): bool
    {
        return static::allow(StaffPermission::WAREHOUSE_MANAGE);
    }
}
