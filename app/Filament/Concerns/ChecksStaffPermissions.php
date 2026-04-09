<?php

namespace App\Filament\Concerns;

use App\Models\Staff;

trait ChecksStaffPermissions
{
    protected static function staff(): ?Staff
    {
        $user = auth('staff')->user();

        return $user instanceof Staff ? $user : null;
    }

    protected static function allow(string $permission): bool
    {
        $staff = static::staff();

        return $staff !== null && $staff->can($permission);
    }
}
