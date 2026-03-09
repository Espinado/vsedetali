<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCrossNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cross_number',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
