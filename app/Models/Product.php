<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'code',
        'sku',
        'name',
        'slug',
        'description',
        'short_description',
        'weight',
        'price',
        'cost_price',
        'vat_rate',
        'is_active',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('sort');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'product_vehicle')
            ->withPivot('oem_number')
            ->withTimestamps();
    }

    public function productVehicles(): HasMany
    {
        return $this->hasMany(ProductVehicle::class);
    }

    public function oemNumbers(): HasMany
    {
        return $this->hasMany(ProductOemNumber::class);
    }

    public function crossNumbers(): HasMany
    {
        return $this->hasMany(ProductCrossNumber::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function getMainImageAttribute(): ?ProductImage
    {
        return $this->images()->where('is_main', true)->first()
            ?? $this->images()->orderBy('sort')->first();
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->stocks()->get()->sum(fn ($s) => max(0, $s->quantity - ($s->reserved_quantity ?? 0)));
    }

    public function getInStockAttribute(): bool
    {
        return $this->total_stock > 0;
    }
}
