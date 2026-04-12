<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Str;

final class CategoryCatalogSlug
{
    /**
     * Slug для ручного создания в админке: из названия, глобально уникален в таблице categories.
     */
    public static function unique(string $name, ?int $exceptCategoryId = null): string
    {
        $base = Str::slug(trim($name));
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $n = 0;
        do {
            $q = Category::query()->where('slug', $slug);
            if ($exceptCategoryId !== null) {
                $q->where('id', '!=', $exceptCategoryId);
            }
            if (! $q->exists()) {
                break;
            }
            $slug = $base.'-'.(++$n);
        } while (true);

        return Str::limit($slug, 255, '');
    }

    /**
     * Как в импорте метаданных: префикс td-, глобальная уникальность.
     */
    public static function uniqueTechnicalPrefixed(string $name, ?int $parentIdForHashSalt = null): string
    {
        $name = Str::limit(trim($name), 255, '');
        $base = 'td-'.Str::slug($name);
        if ($base === 'td-' || $base === 'td') {
            $base = 'td-cat-'.substr(sha1($name.'|'.(string) $parentIdForHashSalt), 0, 12);
        }

        $slug = $base;
        $i = 0;
        while (Category::query()->where('slug', $slug)->exists()) {
            $i++;
            $slug = $base.'-'.$i;
        }

        return Str::limit($slug, 255, '');
    }
}
