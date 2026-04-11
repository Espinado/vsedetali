<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    public static function get(string $key, $default = null)
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }
            $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
                return static::where('key', $key)->first();
            });
            return $setting?->value ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Название магазина для витрины (шапка, футер, SEO): домен vsedetalki.ru вместо устаревших вариантов из БД.
     */
    public static function storeDisplayName(): string
    {
        $fallback = trim((string) config('app.name')) ?: 'vsedetalki.ru';
        $raw = static::get('store_name', null);
        if ($raw === null || trim((string) $raw) === '') {
            return $fallback;
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === 'vsedetalki.ru') {
            return 'vsedetalki.ru';
        }

        $normalized = Str::lower($trimmed);

        $legacy = [
            'vsedetalki',
            'vsedetali',
            'vsedetali.ru',
            'vse detali',
            'vsē detaļi',
        ];

        foreach ($legacy as $legacyName) {
            if ($normalized === Str::lower($legacyName)) {
                return 'vsedetalki.ru';
            }
        }

        return $trimmed;
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
        Cache::forget("setting.{$key}");
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
