<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Concerns\SyncsPlatformDefaultWarehouse;
use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouse extends EditRecord
{
    use SyncsPlatformDefaultWarehouse;

    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['seller_id'] ?? null)) {
            $data['is_default'] = false;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->enforceSinglePlatformDefaultWarehouse($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
