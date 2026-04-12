<?php

namespace App\Support;

/**
 * Нормализация хоста из .env (частая ошибка на проде: https://admin... вместо admin...).
 */
final class PanelDomain
{
    public static function normalizeEnv(string $key): ?string
    {
        $raw = env($key);
        if (! is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $raw)) {
            $host = parse_url($raw, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? $host : null;
        }

        return $raw;
    }
}
