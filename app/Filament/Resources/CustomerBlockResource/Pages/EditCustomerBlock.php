<?php

namespace App\Filament\Resources\CustomerBlockResource\Pages;

use App\Filament\Resources\CustomerBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerBlock extends EditRecord
{
    protected static string $resource = CustomerBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
