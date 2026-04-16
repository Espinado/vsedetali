<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ограничение выборки товаров по марке/модели/году (как в каталоге и на главной).
 */
final class StorefrontVehicleFilter
{
    /**
     * Марка и (если задана) модель на запросе связи vehicles у товара.
     */
    public static function constrainVehiclesByMakeAndOptionalModel(Builder $vehicleQuery, string $make, string $model): void
    {
        $makeLower = mb_strtolower(trim($make));
        $vehicleQuery->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);

        if (trim($model) === '') {
            return;
        }

        $modelLower = mb_strtolower(trim($model));
        $likeMake = ProductNameVehicleExtractor::sqlLikeContains(trim($make));
        $likeModel = ProductNameVehicleExtractor::sqlLikeContains(trim($model));
        $vehicleQuery->where(function (Builder $w) use ($makeLower, $modelLower, $likeMake, $likeModel) {
            $w->whereRaw('LOWER(TRIM(vehicles.model)) = ?', [$modelLower])
                ->orWhere(function (Builder $inner) use ($makeLower, $likeMake, $likeModel) {
                    $inner->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);
                    ProductNameVehicleExtractor::wherePlaceholderVehicleModel($inner, 'vehicles.model');
                    $inner->whereRaw('LOWER(products.name) LIKE ?', [$likeMake])
                        ->whereRaw('LOWER(products.name) LIKE ?', [$likeModel]);
                });
        });
    }

    /**
     * Ограничение запроса к таблице vehicles (без join products): та же логика марки/модели,
     * что и в {@see constrainVehiclesByMakeAndOptionalModel} для подзапроса внутри whereHas у товара.
     */
    public static function constrainVehicleTableRowsByMakeAndModel(Builder $vehicleQuery, string $make, string $model): void
    {
        $makeLower = mb_strtolower(trim($make));
        $vehicleQuery->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);

        if (trim($model) === '') {
            return;
        }

        $modelLower = mb_strtolower(trim($model));
        $likeMake = ProductNameVehicleExtractor::sqlLikeContains(trim($make));
        $likeModel = ProductNameVehicleExtractor::sqlLikeContains(trim($model));
        $vehicleQuery->where(function (Builder $w) use ($makeLower, $modelLower, $likeMake, $likeModel) {
            $w->whereRaw('LOWER(TRIM(vehicles.model)) = ?', [$modelLower])
                ->orWhere(function (Builder $inner) use ($makeLower, $likeMake, $likeModel) {
                    $inner->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);
                    ProductNameVehicleExtractor::wherePlaceholderVehicleModel($inner, 'vehicles.model');
                    $inner->whereHas('products', function (Builder $pq) use ($likeMake, $likeModel) {
                        $pq->active()
                            ->whereRaw('LOWER(products.name) LIKE ?', [$likeMake])
                            ->whereRaw('LOWER(products.name) LIKE ?', [$likeModel]);
                    });
                });
        });
    }

    /**
     * Год с витрины: при заданных compat_year_* в pivot они главнее диапазона записи ТС.
     */
    public static function applyVehicleYearConstraintForProductVehicleLink(Builder $vehicleQuery, int $vehicleYear): void
    {
        $vehicleQuery->where(function (Builder $w) use ($vehicleYear): void {
            $w->where(function (Builder $p) use ($vehicleYear): void {
                $p->whereNotNull('product_vehicle.compat_year_from')
                    ->whereNotNull('product_vehicle.compat_year_to')
                    ->where('product_vehicle.compat_year_from', '<=', $vehicleYear)
                    ->where('product_vehicle.compat_year_to', '>=', $vehicleYear);
            })->orWhere(function (Builder $v) use ($vehicleYear): void {
                $v->where(function (Builder $pv): void {
                    $pv->whereNull('product_vehicle.compat_year_from')
                        ->orWhereNull('product_vehicle.compat_year_to');
                })->where(function (Builder $rangeQuery) use ($vehicleYear): void {
                    $rangeQuery->whereNull('vehicles.year_from')->orWhere('vehicles.year_from', '<=', $vehicleYear);
                })->where(function (Builder $rangeQuery) use ($vehicleYear): void {
                    $rangeQuery->whereNull('vehicles.year_to')->orWhere('vehicles.year_to', '>=', $vehicleYear);
                });
            });
        });
    }

    public static function constrainProductsByVehicle(Builder $query, string $make, string $model, int $year): Builder
    {
        $make = trim($make);
        if ($make === '') {
            return $query;
        }

        $vehicleYear = $year;

        return $query->whereHas('vehicles', function (Builder $vehicleQuery) use ($make, $model, $vehicleYear) {
            self::constrainVehiclesByMakeAndOptionalModel($vehicleQuery, $make, $model);
            if ($vehicleYear > 0) {
                self::applyVehicleYearConstraintForProductVehicleLink($vehicleQuery, $vehicleYear);
            }
        });
    }

    /**
     * Марка/модель и любой год из диапазона [from, to] (включитель), с учётом pivot compat_year_*.
     */
    public static function constrainProductsByVehicleYearWindow(Builder $query, string $make, string $model, int $yearFrom, int $yearTo): Builder
    {
        $make = trim($make);
        if ($make === '') {
            return $query;
        }
        $from = min($yearFrom, $yearTo);
        $to = max($yearFrom, $yearTo);
        $years = range($from, $to);

        return $query->where(function (Builder $yearScope) use ($years, $make, $model): void {
            foreach ($years as $index => $year) {
                if ($index === 0) {
                    self::constrainProductsByVehicle($yearScope, $make, $model, $year);
                } else {
                    $yearScope->orWhere(function (Builder $orYear) use ($make, $model, $year): void {
                        self::constrainProductsByVehicle($orYear, $make, $model, $year);
                    });
                }
            }
        });
    }

    /**
     * Значение года из селекта/URL: одно число или «YYYY-YYYY».
     *
     * @return array{from: int, to: int}|null
     */
    public static function parseYearSelectionBounds(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}$/', $value) === 1) {
            $y = (int) $value;

            return ['from' => $y, 'to' => $y];
        }
        if (preg_match('/^(\d{4})-(\d{4})$/', $value, $m) === 1) {
            $from = (int) $m[1];
            $to = (int) $m[2];
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return ['from' => $from, 'to' => $to];
        }

        return null;
    }

    /**
     * Границы перебора годов для селекта «Год» (подбор по авто): сужаем окно по year_from/year_to в справочнике.
     *
     * @return array{0: int, 1: int}
     */
    public static function vehicleYearSearchWindowForMakeModel(string $make, string $model): array
    {
        $capFromOpt = config('storefront.vehicle_year_search_from');
        $capFrom = $capFromOpt !== null
            ? (int) $capFromOpt
            : (int) config('storefront.vehicle_year_fallback_from', 1990);
        $capToOpt = config('storefront.vehicle_year_search_to');
        $capTo = $capToOpt !== null
            ? (int) $capToOpt
            : (int) (config('storefront.vehicle_year_fallback_to') ?? (int) now()->format('Y'));

        if ($capFrom > $capTo) {
            [$capFrom, $capTo] = [$capTo, $capFrom];
        }

        $row = Vehicle::query()
            ->whereHas('products', fn (Builder $q) => $q->active())
            ->where(function (Builder $vq) use ($make, $model) {
                self::constrainVehicleTableRowsByMakeAndModel($vq, $make, $model);
            })
            ->selectRaw('MIN(year_from) as min_yf, MAX(year_to) as max_yt')
            ->first();

        $dbMin = $row?->min_yf;
        $dbMax = $row?->max_yt;

        if ($dbMin === null && $dbMax === null) {
            return [$capFrom, $capTo];
        }

        $from = $dbMin !== null ? max($capFrom, (int) $dbMin) : $capFrom;
        $to = $dbMax !== null ? min($capTo, (int) $dbMax) : $capTo;

        if ($from > $to) {
            return [$capFrom, $capTo];
        }

        return [$from, $to];
    }
}
