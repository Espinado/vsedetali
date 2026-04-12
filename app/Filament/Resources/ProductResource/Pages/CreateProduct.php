<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Support\ProductCatalogSlug;
use App\Support\SellerListingVehicleCompatibilities;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @var array<int, array{compat_year_from: ?int, compat_year_to: ?int}>|null
     */
    protected ?array $pendingVehicleSync = null;

    /** @var list<string>|null */
    protected ?array $pendingGalleryPaths = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['vehicle_compatibilities'], $data['product_gallery']);

        $brandName = null;
        if (! empty($data['brand_id'])) {
            $brandName = Brand::query()->whereKey($data['brand_id'])->value('name');
        }
        $data['slug'] = ProductCatalogSlug::unique((string) ($data['name'] ?? ''), $brandName !== null ? (string) $brandName : null);

        return $data;
    }

    protected function beforeCreate(): void
    {
        $state = $this->form->getState();
        $rows = SellerListingVehicleCompatibilities::normalizeRepeaterRows(
            $state['vehicle_compatibilities'] ?? null
        );
        $this->pendingVehicleSync = SellerListingVehicleCompatibilities::collectVehiclePivotSync($rows);

        $gallery = SellerListingVehicleCompatibilities::normalizeListingImageUpload($state['product_gallery'] ?? null);
        if ($gallery === []) {
            throw ValidationException::withMessages([
                'product_gallery' => 'Добавьте хотя бы одно изображение товара.',
            ]);
        }
        $this->pendingGalleryPaths = $gallery;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingVehicleSync !== null) {
            $this->record->syncVehiclesPreservingOemAndCompat($this->pendingVehicleSync);
            $this->pendingVehicleSync = null;
        }
        if ($this->pendingGalleryPaths !== null) {
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
        FilamentSweetAlert::flashSuccess('Товар создан');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '';
    }
}
