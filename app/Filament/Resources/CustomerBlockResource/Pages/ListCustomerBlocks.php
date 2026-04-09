<?php

namespace App\Filament\Resources\CustomerBlockResource\Pages;

use App\Filament\Resources\CustomerBlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBlocks extends ListRecords
{
    protected static string $resource = CustomerBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
