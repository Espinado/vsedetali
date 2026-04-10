<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    /**
     * Краткая строка для карточки товара: «BMW X5 (2010–2020), универсал, 2.0 TDI».
     */
    public function shortCompatibilityLabel(): string
    {
        $parts = array_filter([
            trim((string) $this->make),
            trim((string) $this->model),
        ], fn (string $s) => $s !== '');
        $name = implode(' ', $parts);
        if ($this->generation !== null && trim((string) $this->generation) !== '') {
            $name = trim($name.' '.trim((string) $this->generation));
        }
        if ($name === '') {
            return '';
        }
        if ($this->year_from !== null || $this->year_to !== null) {
            $y1 = $this->year_from ?? '…';
            $y2 = $this->year_to ?? '…';
            $name .= ' ('.$y1.'–'.$y2.')';
        }

        $detailParts = array_values(array_filter([
            $this->body_type !== null && trim((string) $this->body_type) !== ''
                ? trim((string) $this->body_type)
                : null,
            $this->engine !== null && trim((string) $this->engine) !== ''
                ? trim((string) $this->engine)
                : null,
        ]));

        if ($detailParts !== []) {
            $name .= ', '.implode(', ', $detailParts);
        }

        return $name;
    }

    /**
     * Список годов для multiselect: объединение диапазонов year_from–year_to по марке/модели.
     *
     * @return array<int, string>
     */
    public static function yearOptionsForMakeAndModel(?string $make, ?string $model): array
    {
        if ($make === null || $model === null || trim($make) === '' || trim($model) === '') {
            return [];
        }

        $years = collect();
        static::query()
            ->where('make', $make)
            ->where('model', $model)
            ->get()
            ->each(function (self $v) use ($years): void {
                $from = $v->year_from;
                $to = $v->year_to;
                if ($from === null && $to === null) {
                    return;
                }
                if ($from === null) {
                    $from = $to;
                }
                if ($to === null) {
                    $to = $from;
                }
                for ($y = (int) $from; $y <= (int) $to; $y++) {
                    $years->push($y);
                }
            });

        $out = [];
        foreach ($years->unique()->sort()->values() as $y) {
            $out[(int) $y] = (string) $y;
        }

        return $out;
    }

    /**
     * ID записей справочника, чей диапазон годов пересекается с выбранными годами.
     *
     * @param  list<int>  $years
     * @return Collection<int, int>
     */
    public static function idsMatchingMakeModelYears(string $make, string $model, array $years): Collection
    {
        if ($years === []) {
            return collect();
        }

        return static::query()
            ->where('make', $make)
            ->where('model', $model)
            ->get()
            ->filter(function (self $v) use ($years): bool {
                foreach ($years as $y) {
                    $from = $v->year_from ?? 1900;
                    $to = $v->year_to ?? 2100;
                    if ($y >= $from && $y <= $to) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id');
    }
}
