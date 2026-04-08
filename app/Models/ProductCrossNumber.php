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
        'manufacturer_name',
    ];

    /**
     * Краткая подпись для витрины: производитель аналога + номер (из API импорта).
     */
    public function storefrontAnalogLabel(): string
    {
        $num = (string) $this->cross_number;
        $m = trim((string) ($this->manufacturer_name ?? ''));

        return $m !== '' ? $m.' — '.$num : $num;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
