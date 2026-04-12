<?php

namespace App\Livewire\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Support\ProductNameVehicleExtractor;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class ProductGrid extends Component
{
    use WithPagination;

    /** @var string|null Slug категории из URL (опционально) */
    public ?string $categorySlug = null;

    /** @var int ID выбранного бренда в фильтре (0 = все бренды) */
    public int $brandId = 0;

    /** @var string Сортировка: price_asc, price_desc, name_asc, name_desc, newest, brand_asc */
    public string $sort = 'name_asc';

    /** @var string Поиск по названию/артикулу */
    public string $search = '';

    /** @var string Выбранная марка автомобиля */
    public string $vehicleMake = '';

    /** @var string Выбранная модель автомобиля */
    public string $vehicleModel = '';

    /** @var int Выбранный год автомобиля */
    public int $vehicleYear = 0;

    /** @var int Точная запись авто (поколение/годы) — ссылка с карточки товара */
    public int $vehicleId = 0;

    /** @var string Минимальная цена */
    public string $priceFrom = '';

    /** @var string Максимальная цена */
    public string $priceTo = '';

    /** @var bool Только товары в наличии */
    public bool $inStockOnly = false;

    /** Число товаров на странице каталога */
    public int $perPage = 12;

    /** Страница открыта как /vehicle/{id} — подбор по авто зафиксирован */
    public bool $vehiclePageContext = false;

    /** Подпись авто (как в справочнике ТС), для заголовка и чипов */
    public string $vehicleLockLabel = '';

    protected $queryString = [
        'categorySlug' => ['except' => ''],
        'brandId'      => ['except' => 0],
        'sort'         => ['except' => 'name_asc'],
        'search'       => ['except' => ''],
        'vehicleMake'  => ['except' => ''],
        'vehicleModel' => ['except' => ''],
        'vehicleYear'  => ['except' => 0],
        'vehicleId'    => ['except' => 0],
        'priceFrom'    => ['except' => ''],
        'priceTo'      => ['except' => ''],
        'inStockOnly'  => ['except' => false],
        'perPage'      => ['except' => 12],
    ];

    /** Значение с формы и из URL всегда приводим к int */
    protected function casts(): array
    {
        return [
            'brandId' => 'integer',
            'vehicleYear' => 'integer',
            'vehicleId' => 'integer',
            'inStockOnly' => 'boolean',
            'perPage' => 'integer',
        ];
    }

    public function mount(?string $categorySlug = null, ?Vehicle $vehicle = null): void
    {
        $this->perPage = $this->normalizePerPage((int) $this->perPage);

        if ($vehicle !== null) {
            $this->vehiclePageContext = true;
            $this->vehicleId = $vehicle->id;
            $label = $vehicle->shortCompatibilityLabel();
            $this->vehicleLockLabel = $label !== '' ? $label : '';
            $this->applyVehicleToSelectors($vehicle);
        }

        if ($this->isCatalogHiddenCategorySlug($categorySlug)) {
            if ($vehicle !== null) {
                $this->redirect(route('vehicle.parts', ['vehicle' => $vehicle->id]), navigate: true);
            } else {
                $this->redirect(route('catalog'), navigate: true);
            }

            return;
        }

        $this->categorySlug = $categorySlug;

        if ($vehicle === null && $this->vehicleId > 0) {
            $found = Vehicle::query()->find($this->vehicleId);
            if ($found) {
                $this->vehiclePageContext = true;
                $this->applyVehicleToSelectors($found);
                $label = $found->shortCompatibilityLabel();
                $this->vehicleLockLabel = $label !== '' ? $label : '';
            } else {
                $this->vehicleId = 0;
                $this->vehicleLockLabel = '';
            }
        }
    }

    private function applyVehicleToSelectors(Vehicle $vehicle): void
    {
        $this->vehicleMake = (string) $vehicle->make;
        $this->vehicleModel = (string) $vehicle->model;
        if ($vehicle->year_from !== null && $vehicle->year_to !== null) {
            $this->vehicleYear = (int) floor(($vehicle->year_from + $vehicle->year_to) / 2);
        } elseif ($vehicle->year_from !== null) {
            $this->vehicleYear = (int) $vehicle->year_from;
        } elseif ($vehicle->year_to !== null) {
            $this->vehicleYear = (int) $vehicle->year_to;
        } else {
            $this->vehicleYear = 0;
        }
    }

    /**
     * Служебные категории импорта (slug import-*) не участвуют в каталоге на витрине.
     */
    protected function isCatalogHiddenCategorySlug(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }

        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');

        return str_starts_with($slug, $prefix);
    }

    public function getCategoryProperty(): ?Category
    {
        if (! $this->categorySlug || $this->isCatalogHiddenCategorySlug($this->categorySlug)) {
            return null;
        }

        return Category::where('slug', $this->categorySlug)->active()->first();
    }

    /** Цепочка категорий от корня до текущей (для хлебных крошек). */
    public function getCategoryBreadcrumbChainProperty(): Collection
    {
        $category = $this->category;

        return $category ? $category->ancestorsChainForStorefront() : collect();
    }

    public function getRootCategoriesProperty()
    {
        $prefix = (string) config('storefront.hidden_category_slug_prefix', 'import-');
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix).'%';

        return Category::query()
            ->active()
            ->roots()
            ->where('slug', 'not like', $like)
            ->with(['children' => function ($q) use ($like) {
                $q->where('is_active', true)->where('slug', 'not like', $like);
            }])
            ->orderBy('sort')
            ->get();
    }

    /**
     * Число пунктов в сайдбаре «Категории» для счётчика в скобках: корни + подкатегории
     * (без пункта «Все товары»).
     */
    public function getCategoryAccordionItemCountProperty(): int
    {
        $total = 1;
        foreach ($this->rootCategories as $root) {
            $total += 1 + $root->children->count();
        }

        return max(0, $total - 1);
    }

    /**
     * Число пунктов в «Бренд» для счётчика: только реальные бренды (без «Все бренды»).
     */
    public function getBrandAccordionItemCountProperty(): int
    {
        return $this->brandsInCategory->count();
    }

    public function getBrandsInCategoryProperty()
    {
        $query = Brand::active()->whereHas('products', function (Builder $q) {
            $this->applyProductFilters($q, ['brand']);
        })->orderBy('name');

        return $query->get();
    }

    public function getVehicleMakesProperty()
    {
        return Vehicle::query()
            ->whereHas('products', function (Builder $query) {
                $this->applyProductFilters($query, ['vehicleMake', 'vehicleModel', 'vehicleYear']);
            })
            ->select('make')
            ->distinct()
            ->orderBy('make')
            ->pluck('make');
    }

    public function getVehicleModelsProperty()
    {
        if ($this->vehicleMake === '') {
            return collect();
        }

        $make = trim($this->vehicleMake);
        $makeLower = mb_strtolower($make);

        $fromDb = Vehicle::query()
            ->whereRaw('LOWER(make) = ?', [$makeLower])
            ->whereHas('products', function (Builder $query) {
                $this->applyProductFilters($query, ['vehicleModel', 'vehicleYear']);
            })
            ->select('model')
            ->distinct()
            ->pluck('model')
            ->map(fn ($m) => trim((string) $m))
            ->filter(fn (string $m) => $m !== '' && ! ProductNameVehicleExtractor::isPlaceholderVehicleModel($m))
            ->unique()
            ->values();

        $filteredIds = Product::query()->active();
        $this->applyProductFilters($filteredIds, ['vehicleModel', 'vehicleYear']);

        $fromNames = Product::query()->active()
            ->whereIn('products.id', $filteredIds->select('products.id'))
            ->whereHas('vehicles', function (Builder $vq) use ($makeLower) {
                $vq->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);
                ProductNameVehicleExtractor::wherePlaceholderVehicleModel($vq, 'vehicles.model');
            })
            ->pluck('name')
            ->map(fn (string $n) => ProductNameVehicleExtractor::tailAfterMake($n, $make))
            ->filter()
            ->unique()
            ->values();

        return $fromDb->merge($fromNames)->unique()->sort()->values();
    }

    public function getVehicleYearsProperty()
    {
        if ($this->vehicleMake === '' || $this->vehicleModel === '') {
            return collect();
        }

        $years = Vehicle::query()
            ->whereRaw('LOWER(make) = ?', [mb_strtolower(trim($this->vehicleMake))])
            ->whereHas('products', function (Builder $query) {
                $this->applyProductFilters($query, ['vehicleYear']);
            })
            ->get(['year_from', 'year_to'])
            ->flatMap(function (Vehicle $vehicle) {
                // Не размножаем один календарный год на каждую запись с пустыми годами в БД
                // (раньше подставлялся «текущий год» и везде светился 2026).
                if ($vehicle->year_from === null && $vehicle->year_to === null) {
                    return [];
                }

                $from = $vehicle->year_from ?? $vehicle->year_to;
                $to = $vehicle->year_to ?? $vehicle->year_from;

                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }

                return range($from, $to);
            })
            ->unique()
            ->sortDesc()
            ->values();

        if ($years->isEmpty() && (bool) config('storefront.vehicle_year_fallback_when_empty', true)) {
            $from = max(1900, (int) config('storefront.vehicle_year_fallback_from', 1990));
            $toOpt = config('storefront.vehicle_year_fallback_to');
            $to = $toOpt !== null
                ? max($from, (int) $toOpt)
                : (int) now()->format('Y');
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return collect(range($from, $to))->sortDesc()->values();
        }

        return $years;
    }

    public function getSelectedVehicleLabelProperty(): ?string
    {
        if ($this->vehicleLockLabel !== '') {
            return $this->vehicleLockLabel;
        }

        if ($this->vehicleMake === '') {
            return null;
        }

        $parts = [$this->vehicleMake];

        if ($this->vehicleModel !== '') {
            $parts[] = $this->vehicleModel;
        }

        if ($this->vehicleYear > 0) {
            $parts[] = (string) $this->vehicleYear;
        }

        return implode(' ', $parts);
    }

    /** @return list<int> */
    public function getPerPageOptionsProperty(): array
    {
        return self::allowedPerPageList();
    }

    /** @return list<int> */
    protected static function allowedPerPageList(): array
    {
        return [12, 24, 36, 48];
    }

    protected function normalizePerPage(int $value): int
    {
        return in_array($value, self::allowedPerPageList(), true) ? $value : 12;
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizePerPage((int) $this->perPage);
        $this->resetPage();
    }

    public function getProductsProperty()
    {
        $query = Product::active()
            ->with(['category', 'brand', 'images', 'stocks', 'oemNumbers', 'crossNumbers', 'vehicles']);

        $this->applyProductFilters($query);

        $brandSubquery = '(SELECT name FROM brands WHERE brands.id = products.brand_id)';

        match ($this->sort) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc'   => $query->orderBy('name'),
            'name_desc'  => $query->orderByDesc('name'),
            'newest'     => $query->orderByDesc('created_at'),
            'brand_asc'  => $query->orderByRaw("{$brandSubquery} ASC"),
            'brand_desc' => $query->orderByRaw("{$brandSubquery} DESC"),
            default     => $query->orderBy('name'),
        };

        return $query->paginate($this->normalizePerPage((int) $this->perPage));
    }

    protected function applyProductFilters(Builder $query, array $except = []): Builder
    {
        $brandId = (int) $this->brandId;
        $vehicleYear = (int) $this->vehicleYear;
        $search = trim($this->search);

        return $query
            ->when(! in_array('category', $except, true) && $this->category, fn (Builder $q) => $q->where('category_id', $this->category->id))
            ->when(! in_array('brand', $except, true) && $brandId > 0, fn (Builder $q) => $q->where('brand_id', $brandId))
            ->when(! in_array('vehicleMake', $except, true), function (Builder $q) use ($vehicleYear, $except) {
                if ($this->vehicleId > 0) {
                    $q->whereHas('vehicles', function (Builder $vehicleQuery) {
                        $vehicleQuery->where('vehicles.id', $this->vehicleId);
                    });
                } elseif ($this->vehicleMake !== '') {
                    $q->whereHas('vehicles', function (Builder $vehicleQuery) use ($vehicleYear, $except) {
                        $makeLower = mb_strtolower(trim($this->vehicleMake));
                        $vehicleQuery->whereRaw('LOWER(vehicles.make) = ?', [$makeLower])
                            ->when(! in_array('vehicleModel', $except, true) && $this->vehicleModel !== '', function (Builder $modelQuery) use ($makeLower) {
                                $modelLower = mb_strtolower(trim($this->vehicleModel));
                                $likeMake = ProductNameVehicleExtractor::sqlLikeContains(trim($this->vehicleMake));
                                $likeModel = ProductNameVehicleExtractor::sqlLikeContains(trim($this->vehicleModel));
                                $modelQuery->where(function (Builder $w) use ($makeLower, $modelLower, $likeMake, $likeModel) {
                                    $w->whereRaw('LOWER(TRIM(vehicles.model)) = ?', [$modelLower])
                                        ->orWhere(function (Builder $inner) use ($makeLower, $likeMake, $likeModel) {
                                            $inner->whereRaw('LOWER(vehicles.make) = ?', [$makeLower]);
                                            ProductNameVehicleExtractor::wherePlaceholderVehicleModel($inner, 'vehicles.model');
                                            $inner->whereRaw('LOWER(products.name) LIKE ?', [$likeMake])
                                                ->whereRaw('LOWER(products.name) LIKE ?', [$likeModel]);
                                        });
                                });
                            })
                            ->when(! in_array('vehicleYear', $except, true) && $vehicleYear > 0, function (Builder $yearQuery) use ($vehicleYear) {
                                $yearQuery->where(function (Builder $rangeQuery) use ($vehicleYear) {
                                    $rangeQuery->whereNull('year_from')->orWhere('year_from', '<=', $vehicleYear);
                                })->where(function (Builder $rangeQuery) use ($vehicleYear) {
                                    $rangeQuery->whereNull('year_to')->orWhere('year_to', '>=', $vehicleYear);
                                });
                            });
                    });
                }
            })
            ->when(! in_array('priceFrom', $except, true) && is_numeric($this->priceFrom), fn (Builder $q) => $q->where('price', '>=', (float) $this->priceFrom))
            ->when(! in_array('priceTo', $except, true) && is_numeric($this->priceTo), fn (Builder $q) => $q->where('price', '<=', (float) $this->priceTo))
            ->when(! in_array('inStockOnly', $except, true) && $this->inStockOnly, function (Builder $q) {
                $q->whereHas('stocks', function (Builder $stockQuery) {
                    $stockQuery->whereRaw('quantity > reserved_quantity');
                });
            })
            ->when(! in_array('search', $except, true) && $search !== '', function (Builder $q) use ($search) {
                $term = '%' . $search . '%';
                $q->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhereHas('oemNumbers', function (Builder $q) use ($term) {
                            $q->where('oem_number', 'like', $term);
                        })
                        ->orWhereHas('crossNumbers', function (Builder $q) use ($term) {
                            $q->where('cross_number', 'like', $term)
                                ->orWhere('manufacturer_name', 'like', $term);
                        });
                });
            });
    }

    public function updatedCategorySlug(): void
    {
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedBrandId(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedVehicleMake(): void
    {
        $this->vehicleId = 0;
        $this->vehicleModel = '';
        $this->vehicleYear = 0;
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedVehicleModel(): void
    {
        $this->vehicleId = 0;
        $this->vehicleYear = 0;
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedVehicleYear(): void
    {
        $this->vehicleId = 0;
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedPriceFrom(): void
    {
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedPriceTo(): void
    {
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedInStockOnly(): void
    {
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->brandId = 0;
        $this->search = '';
        $this->sort = 'name_asc';
        $this->priceFrom = '';
        $this->priceTo = '';
        $this->inStockOnly = false;
        if (! $this->vehiclePageContext) {
            $this->vehicleMake = '';
            $this->vehicleModel = '';
            $this->vehicleYear = 0;
            $this->vehicleId = 0;
            $this->vehicleLockLabel = '';
        }
        $this->resetPage();
    }

    protected function syncDependentFilters(): void
    {
        if ($this->brandId > 0 && ! $this->brandsInCategory->contains('id', $this->brandId)) {
            $this->brandId = 0;
        }

        if ($this->vehiclePageContext) {
            return;
        }

        if ($this->vehicleMake !== '' && ! $this->vehicleMakes->contains($this->vehicleMake)) {
            $this->vehicleMake = '';
            $this->vehicleModel = '';
            $this->vehicleYear = 0;

            return;
        }

        if ($this->vehicleModel !== '' && ! $this->vehicleModels->contains($this->vehicleModel)) {
            $this->vehicleModel = '';
            $this->vehicleYear = 0;

            return;
        }

        if ($this->vehicleYear > 0 && ! $this->vehicleYears->contains($this->vehicleYear)) {
            $this->vehicleYear = 0;
        }
    }

    public function render()
    {
        $category = $this->category;
        $storeName = Setting::storeDisplayName();

        $vehicleEntity = $this->vehicleId > 0 ? Vehicle::query()->find($this->vehicleId) : null;
        $vehicleLabelForTitle = $this->selectedVehicleLabel;
        $isVehicleCatalogHead = $vehicleEntity !== null && $this->vehicleLockLabel !== '';

        if ($isVehicleCatalogHead) {
            $titleSegment = $vehicleLabelForTitle
                ? 'Запчасти для '.$vehicleLabelForTitle
                : 'Запчасти для авто';
            if ($category) {
                $titleSegment = ($category->meta_title ?: $category->name).' — '.$titleSegment;
            }
        } else {
            $titleSegment = $category
                ? ($category->meta_title ?: $category->name.' — Каталог')
                : 'Каталог';
        }

        if ($category?->meta_description) {
            $metaDescription = Seo::metaDescription($category->meta_description);
        } elseif ($category) {
            $metaDescription = Seo::metaDescription(
                $category->description,
                'Каталог «'.$category->name.'» в интернет-магазине '.$storeName.'.',
            );
        } elseif ($isVehicleCatalogHead && $vehicleLabelForTitle !== null && $vehicleLabelForTitle !== '') {
            $metaDescription = Seo::metaDescription(
                'Каталог запчастей для '.$vehicleLabelForTitle.' в '.$storeName.'.',
            );
        } else {
            $metaDescription = Setting::get(
                'site_meta_description',
                'Каталог автозапчастей — '.$storeName.'.',
            );
        }

        if ($vehicleEntity !== null) {
            $canonicalUrl = route('vehicle.parts', array_filter([
                'vehicle' => $vehicleEntity,
                'categorySlug' => $this->categorySlug !== null && $this->categorySlug !== '' ? $this->categorySlug : null,
            ], fn ($v) => $v !== null && $v !== ''));
        } else {
            $catalogQuery = array_filter([
                'vehicleId' => $this->vehicleId > 0 ? $this->vehicleId : null,
            ], fn ($v) => $v !== null && $v !== 0);

            $canonicalUrl = $this->categorySlug
                ? route('catalog', array_merge(['categorySlug' => $this->categorySlug], $catalogQuery))
                : ($catalogQuery !== [] ? route('catalog', $catalogQuery) : route('catalog'));
        }

        return view('livewire.storefront.product-grid', [
            'products' => $this->products,
        ])->layout('layouts.storefront', [
            'title' => $titleSegment,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
