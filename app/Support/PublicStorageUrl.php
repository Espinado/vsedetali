<?php

namespace App\Support;

/**
 * URL для файлов в storage/app/public (через symlink public/storage).
 * Использует относительный путь /storage/..., чтобы картинки открывались на текущем домене,
 * даже если APP_URL в .env указывает на другой хост.
 */
final class PublicStorageUrl
{
    public static function from(mixed $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (is_array($path)) {
            $path = $path[0] ?? null;
            if ($path === null || $path === '') {
                return null;
            }
        }

        $path = str_replace('\\', '/', (string) $path);

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^storage/#', '', $path) ?? $path;

        return '/storage/'.$path;
    }
}
