<?php

namespace App\Filament\Seller\Resources\SellerProductResource\Pages;

use App\Filament\Seller\Resources\SellerProductResource;
use App\Models\SellerProduct;
use App\Models\SellerStaff;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSellerProduct extends CreateRecord
{
    protected static string $resource = SellerProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $staff = auth('seller_staff')->user();
        if (! $staff instanceof SellerStaff) {
            throw ValidationException::withMessages(['seller' => 'Нет доступа.']);
        }

        $data['seller_id'] = $staff->seller_id;
        $data['status'] = 'pending';

        $exists = SellerProduct::query()
            ->where('seller_id', $data['seller_id'])
            ->where('product_id', $data['product_id'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'product_id' => 'Этот товар уже добавлен для вашего магазина.',
            ]);
        }

        return $data;
    }
}
