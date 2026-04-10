<?php

namespace App\Filament\Seller\Resources\SellerStaffResource\Pages;

use App\Filament\Seller\Resources\SellerStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellerStaff extends ListRecords
{
    protected static string $resource = SellerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
