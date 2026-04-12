<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Support\PageCatalogSlug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->slug, Page::LOCKED_SLUGS, true)) {
            $data['slug'] = $this->record->slug;
        } else {
            $data['slug'] = PageCatalogSlug::unique((string) ($data['title'] ?? ''), $this->record->id);
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
