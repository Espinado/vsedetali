<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOemNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'oem_number',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
