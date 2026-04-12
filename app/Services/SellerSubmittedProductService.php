<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProduct;
use App\Models\Warehouse;
use App\Support\ProductCatalogSlug;
use App\Support\SellerListingVehicleCompatibilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerSubmittedProductService
{
    /**
     * @param  array{vehicle_compatibilities: list<array{vehicle_make: string, vehicle_model: string, compatibility_years: array<int|string>}>, listing_name: string, listing_images: list<string>|null, price: float|int|string, cost_price?: float|int|string|null, quantity: int|string, oem_code?: string|null, article?: string|null, shipping_days?: int|string|null}  $data
     */
    public function createListing(int $sellerId, array $data): SellerProduct
    {
        $rows = SellerListingVehicleCompatibilities::normalizeRepeaterRows($data['vehicle_compatibilities'] ?? null);

        $name = trim((string) ($data['listing_name'] ?? ''));
        $images = array_values(array_filter($data['listing_images'] ?? []));

        if ($name === '') {
            throw ValidationException::withMessages(['listing_name' => 'Введите название.']);
        }
        if ($images === []) {
            throw ValidationException::withMessages(['listing_images' => 'Загрузите хотя бы одно фото.']);
        }

        $vehicleIds = SellerListingVehicleCompatibilities::collectVehicleIds($rows);

        $warehouseId = Warehouse::query()
            ->where('seller_id', $sellerId)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('id');

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'seller' => 'Нет активного склада продавца. Обратитесь к администрации площадки.',
            ]);
        }

        return DB::transaction(function () use ($sellerId, $data, $name, $vehicleIds, $images, $warehouseId): SellerProduct {
            $category = Category::query()->firstOrCreate(
                ['slug' => Category::MARKETPLACE_MODERATION_SLUG],
                [
                    'name' => 'Маркетплейс (модерация)',
                    'parent_id' => null,
                    'sort' => 9999,
                    'is_active' => false,
                ]
            );

            $fallbackBrand = Brand::platformUnknownFallback();

            $sku = $this->uniqueSku($sellerId);
            $slug = ProductCatalogSlug::unique($name, $fallbackBrand->name);

            $product = Product::query()->create([
                'category_id' => $category->id,
                'brand_id' => $fallbackBrand->id,
                'code' => $sku,
                'sku' => $sku,
                'name' => $name,
                'slug' => $slug,
                'price' => 0,
                'is_active' => false,
                'type' => 'part',
            ]);

            $product->vehicles()->sync($vehicleIds->all());

            foreach ($images as $i => $path) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'sort' => $i,
                    'is_main' => $i === 0,
                ]);
            }

            return SellerProduct::query()->create([
                'seller_id' => $sellerId,
                'product_id' => $product->id,
                'price' => $data['price'],
                'cost_price' => $data['cost_price'],
                'quantity' => (int) $data['quantity'],
                'oem_code' => trim((string) $data['oem_code']),
                'article' => trim((string) $data['article']),
                'shipping_days' => (int) $data['shipping_days'],
                'warehouse_id' => $warehouseId,
                'status' => 'pending',
            ]);
        });
    }

    private function uniqueSku(int $sellerId): string
    {
        do {
            $sku = 'SP-'.$sellerId.'-'.Str::lower(Str::random(10));
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

}
