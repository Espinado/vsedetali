<?php

namespace App\Http\Controllers;

use App\Models\Product;

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

        if (!$product->is_active) {
            abort(404);
        }

        return view('storefront.product.show', compact('product'));
    }
}
