<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Склад площадки: {@see $seller_id} = null — наш основной склад (магазин).
 * Иначе — склад продавца на маркетплейсе (связь с {@see Seller}).
 */
class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_default',
        'is_active',
        'seller_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopePlatformWarehouses(Builder $query): Builder
    {
        return $query->whereNull('seller_id');
    }

    public function scopeSellerWarehouses(Builder $query): Builder
    {
        return $query->whereNotNull('seller_id');
    }

    public function isPlatformWarehouse(): bool
    {
        return $this->seller_id === null;
    }

    public function isSellerWarehouse(): bool
    {
        return $this->seller_id !== null;
    }

    /**
     * Подпись владельца для таблиц/админки (не колонка БД).
     */
    public function getOwnerLabelAttribute(): string
    {
        return $this->isPlatformWarehouse()
            ? 'Площадка (мы)'
            : (string) ($this->seller?->name ?? 'Продавец #'.$this->seller_id);
    }
}
