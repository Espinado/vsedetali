<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
     * Год или диапазон лет применимости для витрины (вторые скобки после марки/модели).
     * Если в БД нет годов — без суффикса.
     */
    public function storefrontYearRangeSuffix(): string
    {
        if ($this->year_from === null && $this->year_to === null) {
            return '';
        }
        if ($this->year_from !== null && $this->year_to !== null) {
            $from = (int) $this->year_from;
            $to = (int) $this->year_to;

            return $from === $to
                ? ' ('.$from.')'
                : ' ('.$from.'–'.$to.')';
        }
        if ($this->year_from !== null) {
            return ' (с '.(int) $this->year_from.')';
        }

        return ' (до '.(int) $this->year_to.')';
    }

    /**
     * Суффикс годов для витрины по явным границам (уточнение в pivot товара).
     */
    public static function storefrontYearRangeSuffixFromValues(?int $yearFrom, ?int $yearTo): string
    {
        if ($yearFrom === null && $yearTo === null) {
            return '';
        }
        if ($yearFrom !== null && $yearTo !== null) {
            $from = (int) $yearFrom;
            $to = (int) $yearTo;
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return $from === $to
                ? ' ('.$from.')'
                : ' ('.$from.'–'.$to.')';
        }
        if ($yearFrom !== null) {
            return ' (с '.(int) $yearFrom.')';
        }

        return ' (до '.(int) $yearTo.')';
    }

    /**
     * Границы применимости по записи справочника для пересечения с выбранными годами (null в БД → открытый конец).
     *
     * @return array{0: int, 1: int}
     */
    public function logicalYearBoundsInclusive(): array
    {
        $from = $this->year_from ?? 1900;
        $to = $this->year_to ?? 2100;
        $from = (int) $from;
        $to = (int) $to;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * Годы из списка, попадающие в границы этой записи ТС.
     *
     * @param  list<int>  $years
     * @return list<int>
     */
    public function intersectYearListWithBounds(array $years): array
    {
        [$vf, $vt] = $this->logicalYearBoundsInclusive();
        $out = [];
        foreach (array_unique(array_map('intval', $years)) as $y) {
            if ($y >= $vf && $y <= $vt) {
                $out[] = $y;
            }
        }
        sort($out);

        return $out;
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
        $name .= $this->storefrontYearRangeSuffix();

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
     * Годы внутри year_from–year_to для подстановки в мультивыбор совместимости.
     *
     * @return list<int>
     */
    public function discreteYearsCovered(): array
    {
        if ($this->year_from === null && $this->year_to === null) {
            return [];
        }
        $from = $this->year_from ?? $this->year_to;
        $to = $this->year_to ?? $this->year_from;
        $from = (int) $from;
        $to = (int) $to;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $out = [];
        for ($y = $from; $y <= $to; $y++) {
            if ($y >= 1900 && $y <= 2100) {
                $out[] = $y;
            }
        }

        return $out;
    }

    /**
     * Значение поля «годы» при открытии формы: массив для мультивыбора (если в справочнике есть годы) или строка для ручного ввода.
     */
    public function compatibilityYearsFormState(): array|string|null
    {
        $opts = static::yearOptionsForMakeAndModel($this->make, $this->model);
        $disc = $this->discreteYearsCovered();
        if ($opts !== []) {
            $allowed = array_flip(array_map('intval', array_keys($opts)));

            return array_values(array_filter($disc, fn (int $y): bool => isset($allowed[$y])));
        }
        if ($disc === []) {
            return null;
        }

        return implode(', ', $disc);
    }

    /**
     * Варианты мультивыбора «годы» — только если в справочнике для пары марка/модель заданы диапазоны; иначе пусто (годы вводятся вручную).
     *
     * @return array<int, string>
     */
    public static function yearSelectOptionsForCompatibilityPicker(?string $make, ?string $model): array
    {
        return self::yearOptionsForMakeAndModel($make, $model);
    }

    /**
     * В справочнике для пары марка/модель заданы диапазоны годов (есть смысл выбирать конкретные строки и мультивыбор лет).
     */
    public static function catalogHasYearRangesForMakeAndModel(?string $make, ?string $model): bool
    {
        return self::yearOptionsForMakeAndModel($make, $model) !== [];
    }

    /**
     * Строки справочника для чекбоксов «записи из справочника»: та же марка/модель и у записи заданы годы (есть что показать в списке).
     *
     * @return Collection<int, self>
     */
    public static function compatibilityPickerRowsWithDefinedYears(?string $make, ?string $model): Collection
    {
        if ($make === null || $model === null || trim($make) === '' || trim($model) === '') {
            return collect();
        }
        if (! static::catalogHasYearRangesForMakeAndModel($make, $model)) {
            return collect();
        }

        return static::query()
            ->where('make', $make)
            ->where('model', $model)
            ->orderBy('generation')
            ->orderBy('year_from')
            ->orderBy('year_to')
            ->orderBy('id')
            ->get()
            ->filter(fn (self $v): bool => $v->discreteYearsCovered() !== [])
            ->values();
    }

    /**
     * Допустимые целые годы для проверки: годы из справочника по паре или весь диапазон 1900–2100, если в справочнике годов нет.
     *
     * @return list<int>
     */
    public static function yearAllowedIntsForCompatibilityPicker(?string $make, ?string $model): array
    {
        $catalog = self::yearOptionsForMakeAndModel($make, $model);
        if ($catalog !== []) {
            return array_map('intval', array_keys($catalog));
        }
        $out = [];
        for ($y = 1900; $y <= 2100; $y++) {
            $out[] = $y;
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

    /**
     * Подпись строки справочника в чекбоксах «записи из справочника» (админка и кабинет продавца).
     * Диапазон годов включается через {@see shortCompatibilityLabel()} (в скобках после модели/поколения).
     */
    public function adminCompatibilityPickerLabel(): string
    {
        $line = $this->shortCompatibilityLabel();
        if ($line !== '') {
            return '#'.$this->id.' — '.$line;
        }

        return '#'.$this->id.' — '.trim($this->make.' '.$this->model);
    }

    public static function normalizedGeneration(?string $generation): ?string
    {
        if ($generation === null) {
            return null;
        }
        $g = trim($generation);

        return $g === '' ? null : $g;
    }

    /**
     * Есть ли уже такая же точка применимости (марка, модель, поколение, год от/до).
     */
    public static function applicabilityDuplicateExists(
        string $make,
        string $model,
        ?string $generation,
        ?int $yearFrom,
        ?int $yearTo,
        ?int $ignoreId = null,
    ): bool {
        $gen = self::normalizedGeneration($generation);
        $q = static::query()
            ->where('make', $make)
            ->where('model', $model);

        if ($gen === null) {
            $q->whereNull('generation');
        } else {
            $q->where('generation', $gen);
        }

        if ($yearFrom === null) {
            $q->whereNull('year_from');
        } else {
            $q->where('year_from', $yearFrom);
        }

        if ($yearTo === null) {
            $q->whereNull('year_to');
        } else {
            $q->where('year_to', $yearTo);
        }

        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function assertApplicabilityUniqueOrThrow(array $attributes, ?int $ignoreId = null): void
    {
        $make = trim((string) ($attributes['make'] ?? ''));
        $model = trim((string) ($attributes['model'] ?? ''));
        if ($make === '' || $model === '') {
            return;
        }

        $gen = self::normalizedGeneration(isset($attributes['generation']) ? (string) $attributes['generation'] : null);
        $yf = self::optionalYearAttribute($attributes['year_from'] ?? null);
        $yt = self::optionalYearAttribute($attributes['year_to'] ?? null);

        if (self::applicabilityDuplicateExists($make, $model, $gen, $yf, $yt, $ignoreId)) {
            throw ValidationException::withMessages([
                'year_from' => 'Запись с такой маркой, моделью, поколением и годами уже есть в справочнике.',
            ]);
        }
    }

    private static function optionalYearAttribute(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
