<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Support\MarketplaceSellerSlug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeller extends EditRecord
{
    protected static string $resource = SellerResource::class;

    public function getSubNavigation(): array
    {
        return SellerResource::getSellerHubSubNavigation();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = MarketplaceSellerSlug::unique(
            (string) ($data['name'] ?? ''),
            $this->record->id,
        );

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
