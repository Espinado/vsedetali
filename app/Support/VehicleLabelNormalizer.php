<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Единый вид марки/модели (Geely == GEELY == geely).
 */
final class VehicleLabelNormalizer
{
    public static function title(string $value): string
    {
        $v = trim($value);

        return $v === '' ? $v : Str::title(Str::lower($v));
    }
}
