<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

final class PageCatalogSlug
{
    public static function unique(string $title, ?int $exceptPageId = null): string
    {
        $base = Str::slug(trim($title));
        if ($base === '') {
            $base = 'page';
        }

        $slug = $base;
        $n = 0;
        do {
            $q = Page::query()->where('slug', $slug);
            if ($exceptPageId !== null) {
                $q->where('id', '!=', $exceptPageId);
            }
            if (! $q->exists()) {
                break;
            }
            $slug = $base.'-'.(++$n);
        } while (true);

        return Str::limit($slug, 255, '');
    }
}
