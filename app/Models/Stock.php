<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'days_in_warehouse',
        'seller_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'days_in_warehouse' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeInWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
