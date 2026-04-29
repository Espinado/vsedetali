<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Отсекает товары, у которых в названии явно указана другая марка из каталога,
 * чем выбранная в подборе (типичный мусор в product_vehicle после ошибочного импорта).
 *
 * Учитываются все вхождения марок из справочника ТС в названии, а не только «первая» по длине строки;
 * плюс явные токены вроде Exeed, которых может не быть в поле vehicles.make.
 */
final class StorefrontVehicleProductNameConsistency
{
    /**
     * Индекс «марка → id ТС с витринно-валидными товарами» + отсортированный список марок.
     * Строится одним SQL вместо N запросов (раньше на каждую марку вызывался тяжёлый join).
     *
     * @var array{by_make_lower: array<string, Collection<int, int>>, makes: Collection<int, string>}|null
     */
    private static ?array $storefrontVisibleVehiclesIndex = null;

    /**
     * ID активных товаров, привязанных к записи ТС (и году по pivot при $vehicleYear > 0),
     * у которых название не содержит явной «чужой» марки относительно выбранной.
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

        $nameKeys = [];

        foreach (ProductNameVehicleExtractor::distinctMakesHavingProducts() as $make) {
            if (ProductNameVehicleExtractor::tailAfterMake($productName, $make) === null) {
                continue;
            }
            $k = self::normalizeMakeKey($make);
            if ($k !== '') {
                $nameKeys[$k] = true;
            }
        }

        foreach (self::normalizedMakeKeysExplicitInProductName($productName) as $k) {
            if ($k !== '') {
                $nameKeys[$k] = true;
            }
        }

        if ($nameKeys === []) {
            return false;
        }

        $selectedKey = self::normalizeMakeKey($selected);

        return $selectedKey === '' || ! isset($nameKeys[$selectedKey]);
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

        if (in_array($m, ['chery', 'чери'], true) || $m === 'exeed') {
            return 'chery_exeed';
        }

        return $m;
    }

    /**
     * Марки/суббренды по тексту названия, если их нет как отдельной строки vehicles.make.
     *
     * @return list<string>
     */
    private static function normalizedMakeKeysExplicitInProductName(string $productName): array
    {
        $lower = mb_strtolower($productName);
        $keys = [];
        if (str_contains($lower, 'exeed')) {
            $keys[] = 'chery_exeed';
        }

        return $keys;
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

        $idx = self::ensureStorefrontVisibleVehicleIndex();

        return $idx['by_make_lower'][$makeLower] ?? collect();
    }

    /**
     * Марки, для которых есть хотя бы одна запись ТС с товарами, пригодными для витрины
     * (та же логика, что {@see vehicleIdsWithStorefrontVisibleProductsForMake} — непустой список модификаций).
     *
     * @return Collection<int, string>
     */
    public static function makesHavingStorefrontVisibleVehicleVariants(): Collection
    {
        return self::ensureStorefrontVisibleVehicleIndex()['makes'];
    }

    public static function clearMakesWithVisibleVariantsCache(): void
    {
        self::$storefrontVisibleVehiclesIndex = null;
    }

    /**
     * @return array{by_make_lower: array<string, Collection<int, int>>, makes: Collection<int, string>}
     */
    private static function ensureStorefrontVisibleVehicleIndex(): array
    {
        if (self::$storefrontVisibleVehiclesIndex !== null) {
            return self::$storefrontVisibleVehiclesIndex;
        }

        $rows = DB::table('product_vehicle')
            ->join('products', 'products.id', '=', 'product_vehicle.product_id')
            ->join('vehicles', 'vehicles.id', '=', 'product_vehicle.vehicle_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->where('categories.is_active', true)
            ->where('categories.slug', 'not like', self::hiddenCategorySlugLike())
            ->select(['vehicles.id as vehicle_id', 'products.name as product_name', 'vehicles.make as vehicle_make'])
            ->orderBy('vehicles.id')
            ->get();

        /** @var array<int, list<\stdClass>> $byVehicle */
        $byVehicle = [];
        foreach ($rows as $row) {
            $vid = (int) $row->vehicle_id;
            if (! isset($byVehicle[$vid])) {
                $byVehicle[$vid] = [];
            }
            $byVehicle[$vid][] = $row;
        }

        /** @var array<string, array<int, true>> $byMake */
        $byMake = [];
        /** @var array<string, string> $canonicalMake */
        $canonicalMake = [];

        foreach ($byVehicle as $vehicleId => $group) {
            $vehicleMake = trim((string) ($group[0]->vehicle_make ?? ''));
            if ($vehicleMake === '') {
                continue;
            }
            $makeLower = mb_strtolower($vehicleMake);
            $ok = false;
            foreach ($group as $row) {
                if (! self::conflictsWithSelectedVehicleMake((string) $row->product_name, $vehicleMake)) {
                    $ok = true;

                    break;
                }
            }
            if (! $ok) {
                continue;
            }
            if (! isset($byMake[$makeLower])) {
                $byMake[$makeLower] = [];
            }
            $byMake[$makeLower][$vehicleId] = true;
            if (! isset($canonicalMake[$makeLower])) {
                $canonicalMake[$makeLower] = $vehicleMake;
            }
        }

        /** @var array<string, Collection<int, int>> $collections */
        $collections = [];
        foreach ($byMake as $ml => $ids) {
            $collections[$ml] = collect(array_keys($ids))->sort()->values();
        }

        $makes = collect($canonicalMake)
            ->values()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        self::$storefrontVisibleVehiclesIndex = [
            'by_make_lower' => $collections,
            'makes' => $makes,
        ];

        return self::$storefrontVisibleVehiclesIndex;
    }

    private static function hiddenCategorySlugLike(): string
    {
        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');

        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';
    }
}
