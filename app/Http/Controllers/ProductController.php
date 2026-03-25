<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Seo;

class ProductController extends Controller
{
    public function show(Product $product)
    {
        $product->load([
            'category',
            'brand',
            'images' => fn ($q) => $q->orderBy('sort'),
            'attributes' => fn ($q) => $q->orderBy('sort'),
            'vehicles',
            'oemNumbers',
            'crossNumbers',
            'stocks',
        ]);

        if (! $product->is_active) {
            abort(404);
        }

        $metaDescription = Seo::metaDescription(
            $product->meta_description,
            $product->short_description,
            $product->description
        );

        $canonicalUrl = route('product.show', $product);

        $ogImageUrl = null;
        if ($product->main_image?->storage_url) {
            $u = $product->main_image->storage_url;
            $ogImageUrl = str_starts_with($u, 'http') ? $u : url($u);
        }

        $currency = Setting::get('currency', 'RUB');

        $productJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => Seo::metaDescription($product->short_description, $product->description) ?: $product->name,
            'url' => $canonicalUrl,
        ];

        if ($product->brand) {
            $productJsonLd['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }

        $imageUrls = $product->images
            ->map(fn ($i) => $i->storage_url ? (str_starts_with($i->storage_url, 'http') ? $i->storage_url : url($i->storage_url)) : null)
            ->filter()
            ->values()
            ->all();

        if ($imageUrls !== []) {
            $productJsonLd['image'] = count($imageUrls) === 1 ? $imageUrls[0] : $imageUrls;
        }

        $productJsonLd['offers'] = [
            '@type' => 'Offer',
            'url' => $canonicalUrl,
            'priceCurrency' => $currency,
            'price' => (string) $product->price,
            'availability' => $product->in_stock
                ? 'https://schema.org/InStock'
                : 'https://schema.org/PreOrder',
        ];

        return view('storefront.product.show', [
            'product' => $product,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
            'ogImageUrl' => $ogImageUrl,
            'ogType' => 'product',
            'productJsonLd' => $productJsonLd,
        ]);
    }
}
