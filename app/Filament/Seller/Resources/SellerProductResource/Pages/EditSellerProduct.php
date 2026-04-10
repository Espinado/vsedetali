<?php

namespace App\Filament\Seller\Resources\SellerProductResource\Pages;

use App\Filament\Seller\Resources\SellerProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSellerProduct extends EditRecord
{
    protected static string $resource = SellerProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['product_id'], $data['seller_id'], $data['status']);

        return $data;
    }
}
