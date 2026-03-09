<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'make',
        'model',
        'generation',
        'year_from',
        'year_to',
        'engine',
        'body_type',
    ];

    protected function casts(): array
    {
        return [
            'year_from' => 'integer',
            'year_to' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_vehicle')
            ->withPivot('oem_number')
            ->withTimestamps();
    }

    public function productVehicles(): HasMany
    {
        return $this->hasMany(ProductVehicle::class);
    }

    public function scopeForMake($query, string $make)
    {
        return $query->where('make', $make);
    }

    public function scopeForModel($query, string $make, string $model)
    {
        return $query->where('make', $make)->where('model', $model);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year_from', '<=', $year)->where('year_to', '>=', $year);
    }
}
