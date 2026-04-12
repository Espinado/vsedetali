<?php

namespace App\Models;

use App\Support\ProductNameVehicleExtractor;
use App\Support\VehicleLabelNormalizer;
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
        'meta_title',
        'meta_description',
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
            ->withPivot(['oem_number', 'compat_year_from', 'compat_year_to'])
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

    /**
     * Поиск товара по номеру детали (SKU): точное совпадение или без пробелов/дефисов.
     */
    public function scopeWhereSkuMatchesPartNumber($query, string $partNumber): void
    {
        $trim = trim($partNumber);
        if ($trim === '') {
            $query->whereRaw('0 = 1');

            return;
        }
        $compact = preg_replace('/[\s\-_\/\.]/u', '', $trim);
        $query->where(function ($q) use ($trim, $compact) {
            $q->where('sku', $trim);
            if ($compact !== '') {
                $q->orWhereRaw(
                    'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(sku, \' \', \'\'), \'-\', \'\'), \'_\', \'\'), \'/\', \'\'), \'.\', \'\') = ?',
                    [$compact]
                );
            }
        });
    }

    /**
     * Кросс-номера, по которым в каталоге есть другой товар (совпадение SKU). Без карточки — не попадают в выдачу.
     *
     * Если у этого товара задана совместимость с авто, в список попадают только товары с пересечением по ТС.
     * Так отсекаются ложные совпадения по номеру (например сайлентблок и рычаг из одной цепочки OEM).
     *
     * @return \Illuminate\Support\Collection<int, object{cross: \App\Models\ProductCrossNumber, linked: \App\Models\Product}>
     */
    public function crossNumbersWithLinkedProducts(): \Illuminate\Support\Collection
    {
        $sourceVehicleIds = $this->relationLoaded('vehicles')
            ? $this->vehicles->pluck('id')->unique()->values()
            : $this->vehicles()->pluck('id')->unique()->values();

        return $this->crossNumbers
            ->map(function (ProductCrossNumber $cn) use ($sourceVehicleIds) {
                $linked = static::query()
                    ->where('is_active', true)
                    ->where('id', '!=', $this->id)
                    ->whereSkuMatchesPartNumber($cn->cross_number)
                    ->when(
                        $sourceVehicleIds->isNotEmpty(),
                        fn ($q) => $q->whereHas(
                            'vehicles',
                            fn ($q2) => $q2->whereKey($sourceVehicleIds)
                        )
                    )
                    ->with([
                        'brand',
                        'images' => fn ($q) => $q->orderBy('sort'),
                        'stocks',
                    ])
                    ->first();

                if ($linked === null) {
                    return null;
                }

                return (object) ['cross' => $cn, 'linked' => $linked];
            })
            ->filter()
            ->values();
    }

    /**
     * Подписи «Совместимость» для сетки каталога: без placeholder «Общее»;
     * если в БД только общая марка — берём модель из названия товара (после марки).
     * Если модель из названия не извлекается — строку не показываем (даже марку).
     *
     * @return list<string>
     */
    public function compatibilityLabelsForStorefrontCard(int $limit = 2): array
    {
        if (! $this->relationLoaded('vehicles')) {
            $this->load('vehicles');
        }

        $out = [];

        foreach ($this->vehicles as $v) {
            if (count($out) >= $limit) {
                break;
            }
            $make = trim((string) $v->make);
            if ($make === '') {
                continue;
            }
            $model = trim((string) $v->model);
            $isPlaceholder = ProductNameVehicleExtractor::isPlaceholderVehicleModel($model);

            if ($isPlaceholder) {
                $tail = ProductNameVehicleExtractor::tailAfterMake((string) $this->name, $make);
                if ($tail === null || $tail === '') {
                    continue;
                }
                $out[] = VehicleLabelNormalizer::title(trim($make.' '.$tail)).$this->storefrontYearSuffixForLinkedVehicle($v);
            } else {
                $base = trim($make.' '.$model);
                if ($v->generation !== null && trim((string) $v->generation) !== '') {
                    $base = trim($base.' '.trim((string) $v->generation));
                }
                $out[] = $base.$this->storefrontYearSuffixForLinkedVehicle($v);
            }
        }

        if ($out === [] && trim((string) $this->name) !== '' && $this->vehicles->isEmpty()) {
            $hit = ProductNameVehicleExtractor::firstMakeAndTailFromName((string) $this->name);
            if ($hit !== null) {
                $out[] = VehicleLabelNormalizer::title(trim($hit['make'].' '.$hit['tail']));
            }
        }

        return $out;
    }

    /**
     * Полная строка совместимости (как {@see Vehicle::shortCompatibilityLabel}, но без «Общее» и с угадыванием модели из названия товара).
     * Для placeholder «Общее» без модели в названии — пустая строка (марку не показываем).
     */
    public function vehicleCompatibilityLineForStorefront(Vehicle $vehicle): string
    {
        $make = trim((string) $vehicle->make);
        $model = trim((string) $vehicle->model);
        if ($make === '') {
            return '';
        }

        $isPlaceholder = ProductNameVehicleExtractor::isPlaceholderVehicleModel($model);

        if ($isPlaceholder) {
            $tail = ProductNameVehicleExtractor::tailAfterMake((string) $this->name, $make);
            if ($tail !== null && $tail !== '') {
                $name = trim($make.' '.$tail);
            } else {
                $hit = ProductNameVehicleExtractor::firstMakeAndTailFromName((string) $this->name);
                if ($hit !== null && mb_strtolower($hit['make']) === mb_strtolower($make)) {
                    $name = trim($make.' '.$hit['tail']);
                } else {
                    return '';
                }
            }
        } else {
            $name = trim($make.' '.$model);
            if ($vehicle->generation !== null && trim((string) $vehicle->generation) !== '') {
                $name = trim($name.' '.trim((string) $vehicle->generation));
            }
        }

        $name .= $this->storefrontYearSuffixForLinkedVehicle($vehicle);

        $detailParts = array_values(array_filter([
            $vehicle->body_type !== null && trim((string) $vehicle->body_type) !== ''
                ? trim((string) $vehicle->body_type)
                : null,
            $vehicle->engine !== null && trim((string) $vehicle->engine) !== ''
                ? trim((string) $vehicle->engine)
                : null,
        ]));

        if ($detailParts !== []) {
            $name .= ', '.implode(', ', $detailParts);
        }

        return $name;
    }

    /**
     * Синхронизирует связи в product_vehicle; для записей, которые уже были привязаны, сохраняет OEM в pivot.
     *
     * @param  iterable<int|string>  $vehicleIds
     */
    public function syncVehiclesByIdsPreservingOem(iterable $vehicleIds): void
    {
        $ids = collect($vehicleIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $existing = $this->vehicles()->get()->keyBy('id');
        $sync = [];
        foreach ($ids as $id) {
            $pivot = $existing->get($id)?->pivot;
            $oem = $pivot && isset($pivot->oem_number) && $pivot->oem_number !== null && trim((string) $pivot->oem_number) !== ''
                ? trim((string) $pivot->oem_number)
                : null;
            $sync[$id] = [
                'oem_number' => $oem,
                'compat_year_from' => null,
                'compat_year_to' => null,
            ];
        }
        $this->vehicles()->sync($sync);
    }

    /**
     * @param  array<int, array{compat_year_from: ?int, compat_year_to: ?int}>  $pivotByVehicleId
     */
    public function syncVehiclesPreservingOemAndCompat(array $pivotByVehicleId): void
    {
        $existing = $this->vehicles()->get()->keyBy('id');
        $sync = [];
        foreach ($pivotByVehicleId as $id => $meta) {
            $id = (int) $id;
            $pivot = $existing->get($id)?->pivot;
            $oem = $pivot && isset($pivot->oem_number) && $pivot->oem_number !== null && trim((string) $pivot->oem_number) !== ''
                ? trim((string) $pivot->oem_number)
                : null;
            $sync[$id] = [
                'oem_number' => $oem,
                'compat_year_from' => $meta['compat_year_from'] ?? null,
                'compat_year_to' => $meta['compat_year_to'] ?? null,
            ];
        }
        $this->vehicles()->sync($sync);
    }

    /**
     * Суффикс лет для строки совместимости: уточнение из pivot или диапазон записи ТС.
     */
    public function storefrontYearSuffixForLinkedVehicle(Vehicle $vehicle): string
    {
        $p = $vehicle->pivot;
        if ($p !== null
            && $p->compat_year_from !== null && $p->compat_year_to !== null) {
            return Vehicle::storefrontYearRangeSuffixFromValues(
                (int) $p->compat_year_from,
                (int) $p->compat_year_to
            );
        }

        return $vehicle->storefrontYearRangeSuffix();
    }

    public function getMainImageAttribute(): ?ProductImage
    {
        if ($this->relationLoaded('images')) {
            $main = $this->images->firstWhere('is_main', true);

            return $main ?? $this->images->sortBy('sort')->first();
        }

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
