<?php

namespace App\Support;

use Illuminate\Support\Str;

class Seo
{
    public static function metaDescription(?string $primary, ?string ...$fallbacks): string
    {
        foreach (array_filter(array_merge([$primary], $fallbacks)) as $text) {
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
            if ($plain !== '') {
                return Str::limit($plain, 160, '');
            }
        }

        return '';
    }
}
