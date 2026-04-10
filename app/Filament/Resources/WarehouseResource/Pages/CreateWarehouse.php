<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Concerns\SyncsPlatformDefaultWarehouse;
use App\Filament\Resources\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    use SyncsPlatformDefaultWarehouse;

    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['seller_id'] ?? null)) {
            $data['is_default'] = false;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->enforceSinglePlatformDefaultWarehouse($this->record);
    }
}
