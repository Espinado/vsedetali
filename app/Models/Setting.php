<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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
