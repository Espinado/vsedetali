<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Support\CategoryCatalogSlug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->slug, Category::LOCKED_SLUGS, true)) {
            $data['slug'] = $this->record->slug;
        } else {
            $data['slug'] = CategoryCatalogSlug::unique((string) ($data['name'] ?? ''), $this->record->id);
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
