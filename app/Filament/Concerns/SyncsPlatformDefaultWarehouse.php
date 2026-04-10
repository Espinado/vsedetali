<?php

namespace App\Filament\Concerns;

use App\Models\Warehouse;

trait SyncsPlatformDefaultWarehouse
{
    protected function enforceSinglePlatformDefaultWarehouse(Warehouse $warehouse): void
    {
        if (! $warehouse->isPlatformWarehouse() || ! $warehouse->is_default) {
            return;
        }

        Warehouse::query()
            ->whereNull('seller_id')
            ->whereKeyNot($warehouse->getKey())
            ->update(['is_default' => false]);
    }
}
