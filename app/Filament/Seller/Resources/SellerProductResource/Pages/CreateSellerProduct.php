<?php

namespace App\Filament\Seller\Resources\SellerProductResource\Pages;

use App\Filament\Seller\Resources\SellerProductResource;
use App\Models\SellerStaff;
use App\Services\SellerSubmittedProductService;
use App\Support\SellerListingVehicleCompatibilities;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateSellerProduct extends CreateRecord
{
    protected static string $resource = SellerProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['vehicle_compatibilities'] = SellerListingVehicleCompatibilities::normalizeRepeaterRows(
            $data['vehicle_compatibilities'] ?? null
        );
        $data['listing_images'] = SellerListingVehicleCompatibilities::normalizeListingImageUpload(
            $data['listing_images'] ?? null
        );

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $staff = auth('seller_staff')->user();
        if (! $staff instanceof SellerStaff) {
            throw ValidationException::withMessages([
                'seller' => 'Нет доступа для создания позиции.',
            ]);
        }

        $messages = [
            'required' => 'Заполните поле «:attribute».',
            'array' => 'Поле «:attribute» должно быть списком.',
            'min.array' => 'Выберите хотя бы одно значение в поле «:attribute».',
            'numeric' => 'Поле «:attribute» должно быть числом.',
            'integer' => 'Поле «:attribute» должно быть целым числом.',
        ];

        $attributes = Lang::get('validation.attributes', [], 'ru');
        if (! is_array($attributes)) {
            $attributes = [];
        }

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

        return app(SellerSubmittedProductService::class)->createListing($staff->seller_id, $data);
    }
}
