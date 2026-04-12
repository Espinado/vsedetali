<?php

namespace App\Support;

use App\Models\Brand;
use Illuminate\Support\Str;

final class BrandCatalogSlug
{
    public static function unique(string $name, ?int $exceptBrandId = null): string
    {
        $base = Str::slug(trim($name));
        if ($base === '') {
            $base = 'brand';
        }

        $slug = $base;
        $n = 0;
        do {
            $q = Brand::query()->where('slug', $slug);
            if ($exceptBrandId !== null) {
                $q->where('id', '!=', $exceptBrandId);
            }
            if (! $q->exists()) {
                break;
            }
            $slug = $base.'-'.(++$n);
        } while (true);

        return Str::limit($slug, 255, '');
    }
}
