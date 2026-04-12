<?php

namespace App\Support;

use App\Models\Seller;
use Illuminate\Support\Str;

final class MarketplaceSellerSlug
{
    public static function unique(string $name, ?int $exceptSellerId = null): string
    {
        $base = Str::slug(trim($name));
        if ($base === '') {
            $base = 'seller';
        }

        $slug = $base;
        $n = 0;
        do {
            $q = Seller::query()->where('slug', $slug);
            if ($exceptSellerId !== null) {
                $q->where('id', '!=', $exceptSellerId);
            }
            if (! $q->exists()) {
                break;
            }
            $slug = $base.'-'.(++$n);
        } while (true);

        return Str::limit($slug, 255, '');
    }
}
