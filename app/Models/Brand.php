<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    public const PLATFORM_UNKNOWN_SLUG = 'platform-brand-unknown';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Бренд-заглушка для строк каталога без производителя (модерация продавца, импорты и т.п.).
     */
    public static function platformUnknownFallback(): self
    {
        return static::query()->firstOrCreate(
            ['slug' => self::PLATFORM_UNKNOWN_SLUG],
            ['name' => 'Без бренда (площадка)', 'is_active' => true],
        );
    }
}
