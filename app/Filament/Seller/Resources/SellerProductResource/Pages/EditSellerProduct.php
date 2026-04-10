<?php

namespace App\Filament\Seller\Resources\SellerProductResource\Pages;

use App\Filament\Seller\Resources\SellerProductResource;
use App\Models\ProductImage;
use App\Models\SellerStaff;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\SellerListingVehicleCompatibilities;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;

class EditSellerProduct extends EditRecord
{
    protected static string $resource = SellerProductResource::class;

    /**
     * @var array{name: string, images: list<string>, vehicle_ids: array<int>}|null
     */
    protected ?array $catalogUpdatePayload = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing(['product.vehicles', 'product.images']);
        $product = $this->record->product;
        if ($product === null) {
            return $data;
        }

        $data['listing_name'] = $product->name;
        $data['listing_images'] = $product->images->sortBy('sort')->pluck('path')->values()->all();

        $rows = $product->vehicles->map(fn (Vehicle $v): array => [
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

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['product_id'], $data['seller_id'], $data['status']);

        $normalized = SellerListingVehicleCompatibilities::normalizeRepeaterRows($data['vehicle_compatibilities'] ?? null);
        $data['vehicle_compatibilities'] = $normalized;

        $data['listing_images'] = SellerListingVehicleCompatibilities::normalizeListingImageUpload(
            $data['listing_images'] ?? null
        );

        $attributes = Lang::get('validation.attributes', [], 'ru');
        if (! is_array($attributes)) {
            $attributes = [];
        }

        $messages = [
            'required' => 'Заполните поле «:attribute».',
            'array' => 'Поле «:attribute» должно быть списком.',
            'min.array' => 'Выберите хотя бы одно значение в поле «:attribute».',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
        ];

        Validator::make(
            $data,
            [
                'vehicle_compatibilities' => ['required', 'array', 'min:1'],
                'vehicle_compatibilities.*.vehicle_make' => ['required', 'string', 'max:100'],
                'vehicle_compatibilities.*.vehicle_model' => ['required', 'string', 'max:100'],
                'vehicle_compatibilities.*.compatibility_years' => ['required', 'array', 'min:1'],
                'vehicle_compatibilities.*.compatibility_years.*' => ['integer', 'min:1900', 'max:2100'],
                'listing_name' => ['required', 'string', 'max:500'],
                'listing_images' => ['required', 'array', 'min:1', 'max:12'],
                'listing_images.*' => ['required', 'string', 'max:500'],
                'price' => ['required', 'numeric', 'min:0'],
                'cost_price' => ['required', 'numeric', 'min:0'],
                'quantity' => ['required', 'integer', 'min:0'],
                'oem_code' => ['required', 'string', 'max:100'],
                'article' => ['required', 'string', 'max:100'],
                'shipping_days' => ['required', 'integer', 'min:0', 'max:999'],
            ],
            $messages,
            $attributes
        )->validate();

        $name = trim((string) ($data['listing_name'] ?? ''));
        $images = array_values(array_filter($data['listing_images'] ?? []));
        $vehicleIds = SellerListingVehicleCompatibilities::collectVehicleIds($normalized)->all();

        $this->catalogUpdatePayload = [
            'name' => $name,
            'images' => $images,
            'vehicle_ids' => $vehicleIds,
        ];

        unset($data['vehicle_compatibilities'], $data['listing_name'], $data['listing_images']);

        $staff = auth('seller_staff')->user();
        if ($staff instanceof SellerStaff) {
            $warehouseId = Warehouse::query()
                ->where('seller_id', $staff->seller_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
            if ($warehouseId !== null) {
                $data['warehouse_id'] = $warehouseId;
            }
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $catalog = $this->catalogUpdatePayload;
        $this->catalogUpdatePayload = null;

        return DB::transaction(function () use ($record, $data, $catalog) {
            $record->update($data);

            if ($catalog !== null && ($product = $record->product)) {
                $product->update(['name' => $catalog['name']]);
                $product->vehicles()->sync($catalog['vehicle_ids']);
                $product->images()->delete();
                foreach ($catalog['images'] as $i => $path) {
                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'sort' => $i,
                        'is_main' => $i === 0,
                    ]);
                }
            }

            return $record->refresh();
        });
    }
}
