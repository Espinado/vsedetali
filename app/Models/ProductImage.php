<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'alt',
        'sort',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'is_main' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Относительный URL для img src (текущий домен + /storage/...).
     */
    public function getStorageUrlAttribute(): ?string
    {
        return PublicStorageUrl::from($this->path);
    }
}
