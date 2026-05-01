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

    /** Токен кэша сырых строк каталога по модификации (подкатегории считаются при клике). */
    public ?string $vinCategoryRowsCacheToken = null;

    /**
     * Карточки текущего уровня (корень или подкатегории выбранной ветки для этой машины).
     *
     * @var list<array{id:int,name:string,has_children:bool}>
     */
    public array $vinCategoryCurrentNodes = [];

    /** @var list<array{id:int,name:string}> */
    public array $vinCategoryPath = [];

    public string $vinCategoriesMessage = '';

    public string $vinCategoryNavMessage = '';

    /** TecDoc car / modification id (поле type_id из listCategoriesByVehicleDescriptor). */
    public ?int $vinCatalogVehicleId = null;

    /** TecDoc manufacturerId для категорий/артикулов RapidAPI (поле manufacturer_id из listCategoriesByVehicleDescriptor). */
    public ?int $vinCatalogManufacturerId = null;

    public ?int $vinSelectedCatalogCategoryId = null;

    /** @var list<array{articleId: int|null, articleNo: string, name: string, supplierName: string, imageUrl: string|null, details: list<array{label: string, value: string}>}> */
    public array $vinCatalogArticles = [];

    public string $vinCatalogArticlesMessage = '';

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

        $this->vinCatalogVehicleId = null;
        $this->vinCatalogManufacturerId = null;
        $this->vinSelectedCatalogCategoryId = null;
        $this->vinCatalogArticles = [];
        $this->vinCatalogArticlesMessage = '';

        /** @var VinDecoderService $decoder */
        $decoder = app(VinDecoderService::class);
        $decoded = $decoder->decode($this->vin);
        $decoded = $this->localizeVinResult($decoded);

        $this->vinDecodeResult = $decoded;
        $this->vinDecodeMessage = ($decoded['success'] ?? false)
            ? (string) ($decoded['message'] ?? '')
            : $this->storefrontServiceUnavailableMessage();

        $this->vinCategories = [];
        $this->vinCategoryRowsCacheToken = null;
        $this->vinCategoryCurrentNodes = [];
        $this->vinCategoryPath = [];
        $this->vinCategoriesMessage = '';
        $this->vinCategoryNavMessage = '';
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
                    $tok = $categoryLookup['category_rows_cache_token'] ?? null;
                    $this->vinCategoryRowsCacheToken = is_string($tok) && $tok !== '' ? $tok : null;
                    // Сотни строк категорий в payload Livewire ломают/замедляют каждый клик по дереву — дерево берём из кэша API.
                    if ($this->vinCategoryRowsCacheToken !== null) {
                        $this->vinCategories = [];
                    }
                    $this->vinCategoriesMessage = ($categoryLookup['found'] ?? false) === true
                        ? ''
                        : $this->storefrontServiceUnavailableMessage();
                    $tid = $categoryLookup['type_id'] ?? null;
                    $this->vinCatalogVehicleId = is_numeric($tid) ? (int) $tid : null;
                    $mid = $categoryLookup['manufacturer_id'] ?? null;
                    $this->vinCatalogManufacturerId = is_numeric($mid) ? (int) $mid : null;
                    $this->refreshVinCategoryCurrentLevel();
                } catch (\Throwable $e) {
                    $this->vinCategoriesMessage = $this->storefrontServiceUnavailableMessage();
                    $this->vinCatalogVehicleId = null;
                    $this->vinCatalogManufacturerId = null;
                    $this->vinCategoryRowsCacheToken = null;
                    $this->vinCategoryCurrentNodes = [];
                    $this->vinCategoryPath = [];
                    $this->vinCategoryNavMessage = '';
                }
            } else {
                $this->vinCategoriesMessage = $this->storefrontServiceUnavailableMessage();
            }
        }
    }

    public function vinCategoryNavigateRoot(): void
    {
        if ($this->vinCategoryPath === []) {
            return;
        }
        $this->vinCategoryPath = [];
        $this->vinSelectedCatalogCategoryId = null;
        $this->vinCatalogArticles = [];
        $this->vinCatalogArticlesMessage = '';
        $this->refreshVinCategoryCurrentLevel();
    }

    public function vinCategoryNavigateUp(): void
    {
        if ($this->vinCategoryPath === []) {
            return;
        }
        array_pop($this->vinCategoryPath);
        $this->vinSelectedCatalogCategoryId = null;
        $this->vinCatalogArticles = [];
        $this->vinCatalogArticlesMessage = '';
        $this->refreshVinCategoryCurrentLevel();
    }

    public function enterVinCategoryBranch(int $nodeId): void
    {
        $nodeId = max(0, $nodeId);
        if ($nodeId <= 0) {
            return;
        }

        $level = $this->vinCategoryCurrentNodes;
        foreach ($level as $node) {
            if (! is_array($node) || (int) ($node['id'] ?? 0) !== $nodeId) {
                continue;
            }
            $hasChildren = (bool) ($node['has_children'] ?? false);
            if ($hasChildren) {
                $name = trim((string) ($node['name'] ?? ''));
                $this->vinCategoryPath[] = [
                    'id' => $nodeId,
                    'name' => $name !== '' ? $name : 'Раздел',
                ];
                $this->vinSelectedCatalogCategoryId = null;
                $this->vinCatalogArticles = [];
                $this->vinCatalogArticlesMessage = '';
                $this->refreshVinCategoryCurrentLevel();

                return;
            }

            $pathIds = $this->vinCategoryPathSegmentIds();
            $pathIds[] = $nodeId;
            $vid = (int) ($this->vinCatalogVehicleId ?? 0);
            $mfr = (int) ($this->vinCatalogManufacturerId ?? 0);
            $token = $this->vinCategoryRowsCacheToken;
            if ($token !== null && $token !== '' && $vid > 0) {
                /** @var AutoPartsCatalogService $catalog */
                $catalog = app(AutoPartsCatalogService::class);
                $aid = $catalog->resolveVinProductGroupLeafArticleId($token, $vid, $mfr, $pathIds);
                $this->selectVinCatalogCategory($aid ?? $nodeId);
            } else {
                $this->selectVinCatalogCategory($nodeId);
            }

            return;
        }
    }

    /**
     * @return list<int>
     */
    protected function vinCategoryPathSegmentIds(): array
    {
        $ids = [];
        foreach ($this->vinCategoryPath as $c) {
            $i = (int) ($c['id'] ?? 0);
            if ($i > 0) {
                $ids[] = $i;
            }
        }

        return $ids;
    }

    protected function refreshVinCategoryCurrentLevel(): void
    {
        $this->vinCategoryCurrentNodes = [];
        $this->vinCategoryNavMessage = '';
        $token = $this->vinCategoryRowsCacheToken;
        if ($token === null || $token === '') {
            return;
        }
        $vid = (int) ($this->vinCatalogVehicleId ?? 0);
        $mfr = (int) ($this->vinCatalogManufacturerId ?? 0);
        if ($vid <= 0) {
            return;
        }
        /** @var AutoPartsCatalogService $catalog */
        $catalog = app(AutoPartsCatalogService::class);
        $res = $catalog->listVinProductGroupChildren($token, $vid, $mfr, $this->vinCategoryPathSegmentIds());
        $this->vinCategoryCurrentNodes = is_array($res['nodes'] ?? null) ? $res['nodes'] : [];
        $err = trim((string) ($res['error'] ?? ''));
        if ($err !== '') {
            $this->vinCategoryNavMessage = $this->storefrontServiceUnavailableMessage();
        }
    }

    public function selectVinCatalogCategory(int $categoryId): void
    {
        $categoryId = max(0, $categoryId);
        $this->vinSelectedCatalogCategoryId = $categoryId > 0 ? $categoryId : null;
        $this->vinCatalogArticles = [];
        $this->vinCatalogArticlesMessage = '';

        $vid = $this->vinCatalogVehicleId;
        if ($vid === null || $vid <= 0 || $categoryId <= 0) {
            $this->vinCatalogArticlesMessage = $this->storefrontServiceUnavailableMessage();

            return;
        }

        /** @var AutoPartsCatalogService $catalog */
        $catalog = app(AutoPartsCatalogService::class);
        if (! $catalog->isConfigured()) {
            $this->vinCatalogArticlesMessage = $this->storefrontServiceUnavailableMessage();

            return;
        }

        try {
            $mfr = (int) ($this->vinCatalogManufacturerId ?? 0);
            $payload = $catalog->listArticlesByVehicleAndCategory($vid, $categoryId, $mfr);
            $this->vinCatalogArticles = is_array($payload['articles'] ?? null) ? $payload['articles'] : [];
            if (($payload['found'] ?? false) === true && $this->vinCatalogArticles !== []) {
                $this->vinCatalogArticlesMessage = '';
            } else {
                $this->vinCatalogArticlesMessage = $this->storefrontServiceUnavailableMessage();
            }
        } catch (\Throwable $e) {
            $this->vinCatalogArticlesMessage = $this->storefrontServiceUnavailableMessage();
        }
    }

    protected function storefrontServiceUnavailableMessage(): string
    {
        return 'Сервис недоступен.';
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
