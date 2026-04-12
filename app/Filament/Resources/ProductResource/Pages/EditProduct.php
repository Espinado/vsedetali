<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Models\Vehicle;
use App\Support\ProductCatalogSlug;
use App\Support\SellerListingVehicleCompatibilities;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @var list<string>|null */
    protected ?array $pendingGalleryPaths = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing(['vehicles', 'images']);
        $rows = $this->record->vehicles->map(fn (Vehicle $v): array => [
            'vehicle_make' => $v->make,
            'vehicle_model' => $v->model,
            'compatibility_years' => SellerListingVehicleCompatibilities::formatVehicleYearsForInput($v),
        ])->values()->all();

        $data['vehicle_compatibilities'] = $rows !== [] ? $rows : [
            [
                'vehicle_make' => null,
                'vehicle_model' => null,
                'compatibility_years' => null,
            ],
        ];

        $data['product_gallery'] = $this->record->images->sortBy('sort')->pluck('path')->values()->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $gallery = SellerListingVehicleCompatibilities::normalizeListingImageUpload($data['product_gallery'] ?? null);
        if ($gallery === []) {
            throw ValidationException::withMessages([
                'product_gallery' => 'Добавьте хотя бы одно изображение товара.',
            ]);
        }
        $this->pendingGalleryPaths = $gallery;

        unset($data['vehicle_compatibilities'], $data['product_gallery']);

        $brandName = null;
        if (! empty($data['brand_id'])) {
            $brandName = Brand::query()->whereKey($data['brand_id'])->value('name');
        }
        $data['slug'] = ProductCatalogSlug::unique(
            (string) ($data['name'] ?? ''),
            $brandName !== null ? (string) $brandName : null,
            $this->record->id,
        );

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '';
    }

    protected function configureDeleteAction(DeleteAction $action): void
    {
        parent::configureDeleteAction($action);

        FilamentSweetAlert::configureHeaderDelete(
            $action,
            'Удалить товар?',
            'Это действие нельзя отменить.',
        );
    }

    protected function afterSave(): void
    {
        $rows = SellerListingVehicleCompatibilities::normalizeRepeaterRows(
            $this->form->getState()['vehicle_compatibilities'] ?? null
        );
        if ($rows === []) {
            $this->record->vehicles()->detach();
        } else {
            $ids = SellerListingVehicleCompatibilities::collectVehicleIds($rows);
            $this->record->syncVehiclesByIdsPreservingOem($ids->all());
        }

        if ($this->pendingGalleryPaths !== null) {
            $this->record->images()->delete();
            foreach ($this->pendingGalleryPaths as $i => $path) {
                ProductImage::query()->create([
                    'product_id' => $this->record->id,
                    'path' => $path,
                    'sort' => $i,
                    'is_main' => $i === 0,
                ]);
            }
            $this->pendingGalleryPaths = null;
        }

        FilamentSweetAlert::flashSuccess('Изменения сохранены');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
