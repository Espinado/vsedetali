<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Resources\BrandResource;
use App\Support\BrandCatalogSlug;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = BrandCatalogSlug::unique((string) ($data['name'] ?? ''));

        return $data;
    }
}
