<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Vehicle::assertApplicabilityUniqueOrThrow($data, $this->record->getKey());

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
