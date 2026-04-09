<?php

namespace App\Filament\Concerns;

use App\Authorization\StaffPermission;

trait AuthorizesContentResource
{
    use ChecksStaffPermissions;

    public static function canViewAny(): bool
    {
        return static::allow(StaffPermission::CONTENT_MANAGE);
    }

    public static function canCreate(): bool
    {
        return static::allow(StaffPermission::CONTENT_MANAGE);
    }

    public static function canEdit($record): bool
    {
        return static::allow(StaffPermission::CONTENT_MANAGE);
    }

    public static function canDelete($record): bool
    {
        return static::allow(StaffPermission::CONTENT_MANAGE);
    }
}
