<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVehicle extends Model
{
    use HasFactory;

    protected $table = 'product_vehicle';

    protected $fillable = [
        'product_id',
        'vehicle_id',
        'oem_number',
        'compat_year_from',
        'compat_year_to',
    ];

    protected function casts(): array
    {
        return [
            'compat_year_from' => 'integer',
            'compat_year_to' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
