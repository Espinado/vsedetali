<?php

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCrossNumber;
use App\Models\Vehicle;
use App\Services\AutoPartsCatalogService;
use App\Services\VinDecoderService;
use App\Support\StorefrontVehicleFilter;
use App\Support\StorefrontVehicleProductNameConsistency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class HomePartFinder extends Component
{
    public string $vehicleMake = '';

    public int $categoryId = 0;

    public int $productId = 0;

    public int $vehicleId = 0;

    /** Поиск из шапки (показываем блок результатов на главной). */
    public string $search = '';

    /** Поиск автомобиля по VIN (вторым блоком под «Поиск по номеру»). */
    public string $vin = '';

    /** @var array<string,mixed>|null */
    public ?array $vinDecodeResult = null;

    public string $vinDecodeMessage = '';

    /** @var list<array{name:string,id:int|null}> */
    public array $vinCategories = [];

    public string $vinCategoriesMessage = '';

    protected $queryString = [
        'vehicleMake' => ['except' => ''],
        'categoryId' => ['except' => 0],
        'productId' => ['except' => 0],
        'vehicleId' => ['except' => 0],
        'search' => ['except' => ''],
    ];

    protected function casts(): array
    {
        return [
            'categoryId' => 'integer',
            'productId' => 'integer',
            'vehicleId' => 'integer',
        ];
    }

    public function mount(): void
    {
        $legacyModel = trim((string) request()->query('vehicleModel', ''));
        $legacyYear = trim((string) request()->query('vehicleYear', ''));

        if ($this->vehicleId > 0) {
            $vehicle = Vehicle::query()
                ->whereKey($this->vehicleId)
                ->whereHas('products', fn (Builder $q) => $q->active())
                ->first();
            if ($vehicle === null) {
                $this->vehicleId = 0;
            } else {
                if (trim($this->vehicleMake) === '') {
                    $this->vehicleMake = (string) $vehicle->make;
                } elseif (mb_strtolower(trim($this->vehicleMake)) !== mb_strtolower(trim((string) $vehicle->make))) {
                    // Марка в URL не совпадает с записью ТС — иначе селекты и категории «уезжают».
                    $this->vehicleMake = (string) $vehicle->make;
                }
            }
        }

        if ($this->vehicleId <= 0 && trim($this->vehicleMake) !== '' && $legacyModel !== '') {
            $this->vehicleId = $this->resolveVehicleIdFromLegacyQuery(
                trim($this->vehicleMake),
                $legacyModel,
                $legacyYear
            );
        }

        $this->syncSelections();
    }

    public function getVehicleMakesProperty(): Collection
    {
        return StorefrontVehicleProductNameConsistency::makesHavingStorefrontVisibleVehicleVariants();
    }

    /**
     * Записи справочника ТС с активными товарами для выбранной марки (одна строка = одна модификация).
     *
     * @return Collection<int, Vehicle>
     */
    public function getVehicleVariantsProperty(): Collection
    {
        if (trim($this->vehicleMake) === '') {
            return collect();
        }

        $makeLower = mb_strtolower(trim($this->vehicleMake));

        $ids = StorefrontVehicleProductNameConsistency::vehicleIdsWithStorefrontVisibleProductsForMake($this->vehicleMake);
        if ($ids->isEmpty()) {
            return collect();
        }

        return Vehicle::query()
            ->whereIn('id', $ids)
            ->whereRaw('LOWER(make) = ?', [$makeLower])
            ->orderByRaw('LOWER(TRIM(model))')
            ->orderByRaw('LOWER(TRIM(COALESCE(generation, "")))')
            ->orderBy('year_from')
            ->orderBy('year_to')
            ->orderBy('id')
            ->get();
    }

    /**
     * Активные товары по выбранному авто с фильтром «марка в названии не противоречит подбору».
     *
     * @return Collection<int, int>
     */
    public function getEligibleProductIdsForHomeFinderProperty(): Collection
    {
        return StorefrontVehicleProductNameConsistency::eligibleActiveProductIdsForVehicle(
            $this->vehicleId,
            $this->vehicleMake,
            0
        );
    }

    public function getSelectedVehicleLabelProperty(): ?string
    {
        if (trim($this->vehicleMake) === '') {
            return null;
        }

        if ($this->vehicleId > 0) {
            $vehicle = Vehicle::query()->find($this->vehicleId);
            if ($vehicle !== null) {
                $line = trim($this->vehicleMake.' '.$vehicle->homePartFinderOptionLabel());

                return $line !== '' ? $line : $this->vehicleMake;
            }
        }

        return $this->vehicleMake;
    }

    public function getCategoriesForVehicleProperty(): Collection
    {
        if (trim($this->vehicleMake) === '' || $this->vehicleId <= 0) {
            return collect();
        }

        $like = $this->hiddenCategorySlugLike();

        $eligible = $this->eligibleProductIdsForHomeFinder;
        if ($eligible->isEmpty()) {
            return collect();
        }

        $ids = Product::query()
            ->active()
            ->whereIn('id', $eligible)
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->active()
            ->whereIn('id', $ids)
            ->where('slug', 'not like', $like)
            ->orderBy('name')
            ->get();
    }

    public function getPartsForCategoryProperty(): Collection
    {
        if ($this->categoryId <= 0 || trim($this->vehicleMake) === '' || $this->vehicleId <= 0) {
            return collect();
        }

        $eligible = $this->eligibleProductIdsForHomeFinder;
        if ($eligible->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->whereIn('id', $eligible)
            ->where('category_id', $this->categoryId)
            ->with(['category', 'brand', 'images', 'stocks', 'crossNumbers', 'vehicles'])
            ->orderBy('name')
            ->get();
    }

    public function getSelectedProductProperty(): ?Product
    {
        if ($this->productId <= 0 || $this->categoryId <= 0) {
            return null;
        }

        $eligible = $this->eligibleProductIdsForHomeFinder;
        if ($eligible->isEmpty() || ! $eligible->contains($this->productId)) {
            return null;
        }

        $q = Product::query()
            ->active()
            ->whereKey($this->productId)
            ->where('category_id', $this->categoryId)
            ->whereIn('id', $eligible);

        return $q->with([
            'category',
            'brand',
            'images' => fn ($q) => $q->orderBy('sort'),
            'stocks',
            'crossNumbers',
            'vehicles',
        ])->first();
    }

    /**
     * Аналоги, по которым в каталоге есть отдельный товар (покупателю не показываем «мертвые» кроссы).
     *
     * @return Collection<int, object{cross: ProductCrossNumber, linked: Product}>
     */
    public function getCrossAnalogRowsProperty(): Collection
    {
        $product = $this->selectedProduct;
        if ($product === null) {
            return collect();
        }

        return $product->crossNumbersWithLinkedProducts(
            forVehicleId: $this->vehicleId > 0 ? $this->vehicleId : null,
            forVehicleMake: trim($this->vehicleMake) !== '' ? $this->vehicleMake : null,
            forVehicleModel: null,
            forVehicleYearFrom: null,
            forVehicleYearTo: null,
        )->map(function (object $item) {
            $item->linked->loadMissing(['category', 'brand', 'images', 'stocks', 'crossNumbers', 'vehicles']);

            return $item;
        });
    }

    public function updatedVehicleMake(): void
    {
        $this->vehicleId = 0;
        $this->categoryId = 0;
        $this->productId = 0;
        $this->syncSelections();
    }

    public function updatedVehicleId(): void
    {
        $this->categoryId = 0;
        $this->productId = 0;
        $this->syncSelections();
    }

    public function updatedCategoryId(): void
    {
        $this->productId = 0;
        $this->syncSelections();
    }

    public function updatedProductId(): void
    {
        $this->syncSelections();
    }

    public function clearSelection(): void
    {
        $this->vehicleMake = '';
        $this->categoryId = 0;
        $this->productId = 0;
        $this->vehicleId = 0;
    }

    public function decodeVin(): void
    {
        $validated = $this->validate([
            'vin' => [
                'required',
                'string',
                'min:11',
                'max:32',
                'not_regex:/<\s*script/i',
                'not_regex:/javascript\s*:/i',
                'not_regex:/on\w+\s*=/i',
            ],
        ]);

        $this->vin = trim((string) $validated['vin']);

        /** @var VinDecoderService $decoder */
        $decoder = app(VinDecoderService::class);
        $decoded = $decoder->decode($this->vin);
        $decoded = $this->localizeVinResult($decoded);

        $this->vinDecodeResult = $decoded;
        $this->vinDecodeMessage = (string) ($decoded['message'] ?? '');

        $this->vinCategories = [];
        $this->vinCategoriesMessage = '';
        $make = trim((string) ($decoded['make'] ?? ''));
        $model = trim((string) ($decoded['model'] ?? ''));
        $year = is_numeric($decoded['model_year'] ?? null) ? (int) $decoded['model_year'] : null;
        if ($make !== '' && $model !== '') {
            /** @var AutoPartsCatalogService $catalog */
            $catalog = app(AutoPartsCatalogService::class);
            if ($catalog->isConfigured()) {
                try {
                    $categoryLookup = $catalog->listCategoriesByVehicleDescriptor($make, $model, $year);
                    $this->vinCategories = is_array($categoryLookup['categories'] ?? null) ? $categoryLookup['categories'] : [];
                    $this->vinCategoriesMessage = (string) ($categoryLookup['message'] ?? '');
                } catch (\Throwable $e) {
                    $this->vinCategoriesMessage = 'Не удалось получить категории из каталога API: '.$e->getMessage();
                }
            } else {
                $this->vinCategoriesMessage = 'RapidAPI каталог не настроен (RAPIDAPI_AUTO_PARTS_KEY).';
            }
        }
    }

    /**
     * @param  array<string,mixed>  $decoded
     * @return array<string,mixed>
     */
    protected function localizeVinResult(array $decoded): array
    {
        $decoded['body_class'] = $this->translateVinValue((string) ($decoded['body_class'] ?? ''), [
            'wagon' => 'Универсал',
            'sedan' => 'Седан',
            'hatchback' => 'Хэтчбек',
            'coupe' => 'Купе',
            'convertible' => 'Кабриолет',
            'suv' => 'Кроссовер / SUV',
            'sport utility vehicle' => 'Кроссовер / SUV',
            'minivan' => 'Минивэн',
            'van' => 'Фургон',
            'pickup' => 'Пикап',
        ]);

        $decoded['fuel_type'] = $this->translateVinValue((string) ($decoded['fuel_type'] ?? ''), [
            'diesel' => 'Дизель',
            'gasoline' => 'Бензин',
            'petrol' => 'Бензин',
            'electric' => 'Электро',
            'hybrid' => 'Гибрид',
            'plug-in hybrid' => 'Подключаемый гибрид',
            'lpg' => 'Газ (LPG)',
            'cng' => 'Метан (CNG)',
        ]);

        $decoded['drivetrain'] = $this->translateVinValue((string) ($decoded['drivetrain'] ?? ''), [
            'front-wheel drive' => 'Передний привод',
            'rear-wheel drive' => 'Задний привод',
            'all-wheel drive' => 'Полный привод',
            'four-wheel drive' => 'Полный привод',
            '4x4' => 'Полный привод',
            'awd' => 'Полный привод',
            'fwd' => 'Передний привод',
            'rwd' => 'Задний привод',
        ]);

        $decoded['transmission'] = $this->translateVinValue((string) ($decoded['transmission'] ?? ''), [
            'manual' => 'Механика',
            'manual/standard' => 'Механика',
            'automatic' => 'Автомат',
            'automatic transmission' => 'Автомат',
            'cvt' => 'Вариатор (CVT)',
            'robot' => 'Робот',
            'dual clutch' => 'Робот (DCT)',
        ]);

        return $decoded;
    }

    protected function translateVinValue(string $value, array $dictionary): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $lower = mb_strtolower($trimmed);
        foreach ($dictionary as $needle => $translated) {
            if (str_contains($lower, mb_strtolower((string) $needle))) {
                return $translated;
            }
        }

        return $trimmed;
    }

    /**
     * Параметры для ссылок на карточку товара (фильтр аналогов по выбранной модификации).
     *
     * @return array<string, int|string>
     */
    public function getProductUrlVehicleQueryProperty(): array
    {
        if ($this->vehicleId <= 0 || trim($this->vehicleMake) === '') {
            return [];
        }

        return array_filter([
            'vehicleId' => $this->vehicleId,
            'vehicleMake' => trim($this->vehicleMake),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Результаты текстового поиска (как в шапке), если в URL есть ?search=
     *
     * @return Collection<int, Product>
     */
    public function getGlobalSearchResultsProperty(): Collection
    {
        $term = trim($this->search);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = '%'.$term.'%';

        return Product::active()
            ->with(['brand', 'images', 'stocks', 'crossNumbers', 'vehicles'])
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhereHas('oemNumbers', function ($q) use ($like) {
                        $q->where('oem_number', 'like', $like);
                    })
                    ->orWhereHas('crossNumbers', function ($q) use ($like) {
                        $q->where('cross_number', 'like', $like)
                            ->orWhere('manufacturer_name', 'like', $like);
                    });
            })
            ->orderBy('name')
            ->limit(36)
            ->get();
    }

    protected function hiddenCategorySlugLike(): string
    {
        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');

        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';
    }

    protected function syncSelections(): void
    {
        if ($this->vehicleMake !== '' && ! $this->vehicleMakes->contains($this->vehicleMake)) {
            $this->clearSelection();

            return;
        }

        $variantIds = $this->vehicleVariants->pluck('id');
        if ($this->vehicleId > 0 && ! $variantIds->contains($this->vehicleId)) {
            $this->vehicleId = 0;
            $this->categoryId = 0;
            $this->productId = 0;
        }

        if ($this->categoryId > 0 && ! $this->categoriesForVehicle->contains('id', $this->categoryId)) {
            $this->categoryId = 0;
            $this->productId = 0;
        }

        if ($this->productId > 0 && ! $this->partsForCategory->contains('id', $this->productId)) {
            $this->productId = 0;
        }
    }

    public function render()
    {
        return view('livewire.storefront.home-part-finder');
    }

    /**
     * Старые ссылки ?vehicleMake=&vehicleModel=&vehicleYear= → vehicleId.
     */
    protected function resolveVehicleIdFromLegacyQuery(string $make, string $model, string $yearSelection): int
    {
        $q = Vehicle::query()
            ->whereHas('products', fn (Builder $pq) => $pq->active())
            ->where(function (Builder $vq) use ($make, $model): void {
                StorefrontVehicleFilter::constrainVehicleTableRowsByMakeAndModel($vq, $make, $model);
            });

        $candidates = $q->orderBy('id')->get();
        if ($candidates->isEmpty()) {
            return 0;
        }

        $bounds = StorefrontVehicleFilter::parseYearSelectionBounds($yearSelection);
        if ($bounds !== null) {
            $filtered = $candidates->filter(function (Vehicle $v) use ($bounds): bool {
                [$vf, $vt] = $v->logicalYearBoundsInclusive();

                return $bounds['to'] >= $vf && $bounds['from'] <= $vt;
            })->values();

            if ($filtered->isEmpty()) {
                return 0;
            }

            return (int) $filtered->first()->id;
        }

        if ($candidates->count() === 1) {
            return (int) $candidates->first()->id;
        }

        return 0;
    }
}
