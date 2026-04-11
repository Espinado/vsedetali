<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ограничение выборки товаров по марке/модели/году (как в каталоге).
 */
final class StorefrontVehicleFilter
{
    public static function constrainProductsByVehicle(Builder $query, string $make, string $model, int $year): Builder
    {
        $make = trim($make);
        if ($make === '') {
            return $query;
        }

        $vehicleYear = $year;

        return $query->whereHas('vehicles', function (Builder $vehicleQuery) use ($make, $model, $vehicleYear) {
            $makeLower = mb_strtolower($make);
            $vehicleQuery->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);

            if ($model !== '') {
                $modelLower = mb_strtolower(trim($model));
                $likeMake = ProductNameVehicleExtractor::sqlLikeContains($make);
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

            if ($vehicleYear > 0) {
                $vehicleQuery
                    ->where(function (Builder $rangeQuery) use ($vehicleYear) {
                        $rangeQuery->whereNull('year_from')->orWhere('year_from', '<=', $vehicleYear);
                    })
                    ->where(function (Builder $rangeQuery) use ($vehicleYear) {
                        $rangeQuery->whereNull('year_to')->orWhere('year_to', '>=', $vehicleYear);
                    });
            }
        });
    }
}
