<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Отсекает товары, у которых в названии явно указана другая марка из каталога,
 * чем выбранная в подборе (типичный мусор в product_vehicle после ошибочного импорта).
 */
final class StorefrontVehicleProductNameConsistency
{
    /**
     * ID активных товаров, привязанных к записи ТС (и году по pivot при $vehicleYear > 0),
     * у которых название не начинается с «чужой» марки из каталога.
     *
     * @return Collection<int, int>
     */
    public static function eligibleActiveProductIdsForVehicle(int $vehicleId, string $vehicleMake, int $vehicleYear = 0): Collection
    {
        if ($vehicleId <= 0) {
            return collect();
        }

        $makeLower = mb_strtolower(trim($vehicleMake));

        $q = Product::query()->active();
        $q->whereHas('vehicles', function (Builder $vehicleQuery) use ($vehicleId, $makeLower, $vehicleYear): void {
            $vehicleQuery->where('vehicles.id', $vehicleId);
            if ($makeLower !== '') {
                $vehicleQuery->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);
            }
            if ($vehicleYear > 0) {
                StorefrontVehicleFilter::applyVehicleYearConstraintForProductVehicleLink($vehicleQuery, $vehicleYear);
            }
        });

        return $q->get(['id', 'name'])
            ->filter(fn (Product $p): bool => ! self::conflictsWithSelectedVehicleMake((string) $p->name, $vehicleMake))
            ->pluck('id')
            ->values();
    }

    /**
     * true — название конфликтует с выбранной маркой (например, в названии Geely, а подбор Lada).
     */
    public static function conflictsWithSelectedVehicleMake(string $productName, string $selectedMake): bool
    {
        $selected = trim($selectedMake);
        if ($selected === '') {
            return false;
        }

        $hit = ProductNameVehicleExtractor::firstMakeAndTailFromName($productName);
        if ($hit === null) {
            return false;
        }

        return ! self::sameMakeFamily($hit['make'], $selected);
    }

    public static function sameMakeFamily(string $makeFromName, string $selectedMake): bool
    {
        $a = self::normalizeMakeKey($makeFromName);
        $b = self::normalizeMakeKey($selectedMake);

        return $a !== '' && $a === $b;
    }

    /**
     * Ключ для сравнения марок (латиница/кириллица, дефисы, семейства Lada/ВАЗ).
     */
    public static function normalizeMakeKey(string $make): string
    {
        $m = mb_strtolower(trim($make));
        if ($m === '') {
            return '';
        }
        $m = str_replace(['-', '–', '—', ' '], '', $m);

        if (in_array($m, ['ваз', 'vaz', 'lada', 'лада'], true)) {
            return 'lada_vaz';
        }

        if (str_contains($m, 'mercedes')) {
            return 'mercedes';
        }

        return $m;
    }

    /**
     * ID записей справочника ТС с данной маркой (без учёта регистра), для которых есть хотя бы один
     * активный товар, пригодный для витрины (название не «ведёт» другой маркой из каталога относительно марки ТС).
     *
     * @return Collection<int, int>
     */
    public static function vehicleIdsWithStorefrontVisibleProductsForMake(string $make): Collection
    {
        $makeLower = mb_strtolower(trim($make));
        if ($makeLower === '') {
            return collect();
        }

        $rows = DB::table('product_vehicle')
            ->join('products', 'products.id', '=', 'product_vehicle.product_id')
            ->join('vehicles', 'vehicles.id', '=', 'product_vehicle.vehicle_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->whereRaw('LOWER(vehicles.make) = ?', [$makeLower])
            ->where('categories.is_active', true)
            ->where('categories.slug', 'not like', self::hiddenCategorySlugLike())
            ->select(['vehicles.id as vehicle_id', 'products.name as product_name', 'vehicles.make as vehicle_make'])
            ->get();

        $out = [];
        foreach ($rows->groupBy('vehicle_id') as $vehicleId => $group) {
            $vehicleMake = trim((string) ($group->first()->vehicle_make ?? ''));
            foreach ($group as $row) {
                if (! self::conflictsWithSelectedVehicleMake((string) $row->product_name, $vehicleMake)) {
                    $out[(int) $vehicleId] = true;

                    break;
                }
            }
        }

        return collect(array_keys($out))->sort()->values();
    }

    private static function hiddenCategorySlugLike(): string
    {
        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');

        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';
    }
}
