<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

final class ProductCatalogSlug
{
    /**
     * Slug из бренда и наименования (кириллица → латиница через {@see Str::slug}).
     */
    public static function unique(string $name, ?string $brandName, ?int $exceptProductId = null): string
    {
        $parts = [];
        if ($brandName !== null && trim($brandName) !== '') {
            $parts[] = trim($brandName);
        }
        if (trim($name) !== '') {
            $parts[] = trim($name);
        }

        $base = Str::slug(implode(' ', $parts));
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $n = 0;
        do {
            $q = Product::query()->where('slug', $slug);
            if ($exceptProductId !== null) {
                $q->where('id', '!=', $exceptProductId);
            }
            if (! $q->exists()) {
                break;
            }
            $slug = $base.'-'.(++$n);
        } while (true);

        return Str::limit($slug, 500, '');
    }
}
