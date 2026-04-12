<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Resources\BrandResource;
use App\Models\Brand;
use App\Support\BrandCatalogSlug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->slug === Brand::PLATFORM_UNKNOWN_SLUG) {
            $data['slug'] = Brand::PLATFORM_UNKNOWN_SLUG;
        } else {
            $data['slug'] = BrandCatalogSlug::unique((string) ($data['name'] ?? ''), $this->record->id);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
