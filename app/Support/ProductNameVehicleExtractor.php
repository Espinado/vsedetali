<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Извлечение марки/модели из наименования товара (когда в БД model = «Общее» или пусто).
 */
final class ProductNameVehicleExtractor
{
    private static ?Collection $makesCache = null;

    public static function clearMakesCache(): void
    {
        self::$makesCache = null;
    }

    public static function defaultModelPlaceholderLower(): string
    {
        return mb_strtolower(trim((string) config('remains_stock_import.default_model_when_missing', 'Общее')));
    }

    public static function isPlaceholderVehicleModel(?string $model): bool
    {
        $m = trim((string) $model);
        if ($m === '') {
            return true;
        }
        $l = mb_strtolower($m);

        return $l === self::defaultModelPlaceholderLower() || $l === 'общее';
    }

    /**
     * Марки из БД (есть активные товары), от длинной к короткой — чтобы матчить «Land Rover» раньше «Land».
     */
    public static function distinctMakesHavingProducts(): Collection
    {
        if (self::$makesCache !== null) {
            return self::$makesCache;
        }

        self::$makesCache = Vehicle::query()
            ->whereHas('products', fn (Builder $q) => $q->where('is_active', true))
            ->distinct()
            ->pluck('make')
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $m) => mb_strlen($m))
            ->values();

        return self::$makesCache;
    }

    /**
     * Текст после марки в наименовании (модель, поколение и т.д.), без хвоста «NEW», года «2019-» и т.п.
     */
    public static function tailAfterMake(string $productName, string $make): ?string
    {
        $name = trim($productName);
        $make = trim($make);
        if ($name === '' || $make === '') {
            return null;
        }

        $lowerName = mb_strtolower($name);
        $lowerMake = mb_strtolower($make);
        $pos = mb_strpos($lowerName, $lowerMake);
        if ($pos === false) {
            return null;
        }

        $after = mb_substr($name, $pos + mb_strlen($make));
        $after = trim(preg_replace('/^[,;\-–—\s]+/u', '', $after));
        if ($after === '') {
            return null;
        }

        $parts = preg_split('/\s+(ДЕФЕКТ|Дефект|DEFECT)/iu', $after);
        $after = trim($parts[0] ?? $after);
        $after = trim(preg_split('/\s*[;,]\s*/u', $after)[0] ?? $after);
        if ($after === '') {
            return null;
        }

        $words = preg_split('/\s+/u', $after);
        $words = array_values(array_filter($words, fn ($w) => $w !== ''));
        $words = self::stripTrailingNoiseWords($words);
        $words = array_slice($words, 0, 10);

        if ($words === []) {
            return null;
        }

        return implode(' ', $words);
    }

    /**
     * Первая подходящая пара марка + хвост по списку марок из каталога.
     *
     * @return array{make: string, tail: string}|null
     */
    public static function firstMakeAndTailFromName(string $productName): ?array
    {
        $name = trim($productName);
        if ($name === '') {
            return null;
        }

        foreach (self::distinctMakesHavingProducts() as $make) {
            $tail = self::tailAfterMake($name, $make);
            if ($tail !== null && $tail !== '') {
                return ['make' => $make, 'tail' => $tail];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $words
     * @return list<string>
     */
    protected static function stripTrailingNoiseWords(array $words): array
    {
        while ($words !== []) {
            $last = $words[array_key_last($words)];
            if (self::isNoiseWord($last)) {
                array_pop($words);

                continue;
            }

            break;
        }

        return $words;
    }

    protected static function isNoiseWord(string $w): bool
    {
        $t = trim($w);
        if ($t === '') {
            return true;
        }
        $l = mb_strtolower($t);

        return (bool) preg_match('/^(new|новый|новая|новое|restyling|рестайлинг|facelift|фейслифт|рестайл)$/iu', $l)
            || (bool) preg_match('/^\d{4}-?$/', $l);
    }

    public static function sqlLikeContains(string $value): string
    {
        $s = mb_strtolower($value);
        $s = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);

        return '%'.$s.'%';
    }

    /** Условие «модель в БД не задана / Общее» для запроса по таблице vehicles. */
    public static function wherePlaceholderVehicleModel(Builder $q, string $column = 'vehicles.model'): void
    {
        $def = self::defaultModelPlaceholderLower();
        $q->where(function (Builder $w) use ($column, $def) {
            $w->whereNull($column)
                ->orWhere($column, '')
                ->orWhereRaw('LOWER(TRIM('.$column.')) = ?', [$def])
                ->orWhereRaw('LOWER(TRIM('.$column.')) = ?', ['общее']);
        });
    }
}
