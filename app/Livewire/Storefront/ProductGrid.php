<?php

namespace App\Livewire\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
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

    /** @var string Минимальная цена */
    public string $priceFrom = '';

    /** @var string Максимальная цена */
    public string $priceTo = '';

    /** @var bool Только товары в наличии */
    public bool $inStockOnly = false;

    protected $queryString = [
        'categorySlug' => ['except' => ''],
        'brandId'      => ['except' => 0],
        'sort'         => ['except' => 'name_asc'],
        'search'       => ['except' => ''],
        'vehicleMake'  => ['except' => ''],
        'vehicleModel' => ['except' => ''],
        'vehicleYear'  => ['except' => 0],
        'priceFrom'    => ['except' => ''],
        'priceTo'      => ['except' => ''],
        'inStockOnly'  => ['except' => false],
    ];

    /** Значение с формы и из URL всегда приводим к int */
    protected function casts(): array
    {
        return [
            'brandId' => 'integer',
            'vehicleYear' => 'integer',
            'inStockOnly' => 'boolean',
        ];
    }

    public function mount(?string $categorySlug = null): void
    {
        if ($this->isCatalogHiddenCategorySlug($categorySlug)) {
            $this->redirect(route('catalog'), navigate: true);

            return;
        }

        $this->categorySlug = $categorySlug;
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

        return Vehicle::query()
            ->where('make', $this->vehicleMake)
            ->whereHas('products', function (Builder $query) {
                $this->applyProductFilters($query, ['vehicleModel', 'vehicleYear']);
            })
            ->select('model')
            ->distinct()
            ->orderBy('model')
            ->pluck('model');
    }

    public function getVehicleYearsProperty()
    {
        if ($this->vehicleMake === '' || $this->vehicleModel === '') {
            return collect();
        }

        $currentYear = (int) now()->format('Y');

        return Vehicle::query()
            ->where('make', $this->vehicleMake)
            ->where('model', $this->vehicleModel)
            ->whereHas('products', function (Builder $query) {
                $this->applyProductFilters($query, ['vehicleYear']);
            })
            ->get(['year_from', 'year_to'])
            ->flatMap(function (Vehicle $vehicle) use ($currentYear) {
                $from = $vehicle->year_from ?: $currentYear;
                $to = $vehicle->year_to ?: $currentYear;

                if ($from > $to) {
                    [$from, $to] = [$to, $from];
                }

                return range($from, $to);
            })
            ->unique()
            ->sortDesc()
            ->values();
    }

    public function getSelectedVehicleLabelProperty(): ?string
    {
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

        return $query->paginate(12);
    }

    protected function applyProductFilters(Builder $query, array $except = []): Builder
    {
        $brandId = (int) $this->brandId;
        $vehicleYear = (int) $this->vehicleYear;
        $search = trim($this->search);

        return $query
            ->when(! in_array('category', $except, true) && $this->category, fn (Builder $q) => $q->where('category_id', $this->category->id))
            ->when(! in_array('brand', $except, true) && $brandId > 0, fn (Builder $q) => $q->where('brand_id', $brandId))
            ->when(! in_array('vehicleMake', $except, true) && $this->vehicleMake !== '', function (Builder $q) use ($vehicleYear, $except) {
                $q->whereHas('vehicles', function (Builder $vehicleQuery) use ($vehicleYear, $except) {
                    $vehicleQuery->where('make', $this->vehicleMake)
                        ->when(! in_array('vehicleModel', $except, true) && $this->vehicleModel !== '', fn (Builder $modelQuery) => $modelQuery->where('model', $this->vehicleModel))
                        ->when(! in_array('vehicleYear', $except, true) && $vehicleYear > 0, function (Builder $yearQuery) use ($vehicleYear) {
                            $yearQuery->where(function (Builder $rangeQuery) use ($vehicleYear) {
                                $rangeQuery->whereNull('year_from')->orWhere('year_from', '<=', $vehicleYear);
                            })->where(function (Builder $rangeQuery) use ($vehicleYear) {
                                $rangeQuery->whereNull('year_to')->orWhere('year_to', '>=', $vehicleYear);
                            });
                        });
                });
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
                            $q->where('cross_number', 'like', $term);
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
        $this->vehicleModel = '';
        $this->vehicleYear = 0;
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedVehicleModel(): void
    {
        $this->vehicleYear = 0;
        $this->syncDependentFilters();
        $this->resetPage();
    }

    public function updatedVehicleYear(): void
    {
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
        $this->vehicleMake = '';
        $this->vehicleModel = '';
        $this->vehicleYear = 0;
        $this->priceFrom = '';
        $this->priceTo = '';
        $this->inStockOnly = false;
        $this->resetPage();
    }

    protected function syncDependentFilters(): void
    {
        if ($this->brandId > 0 && ! $this->brandsInCategory->contains('id', $this->brandId)) {
            $this->brandId = 0;
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
        $storeName = Setting::get('store_name', config('app.name'));

        $titleSegment = $category
            ? ($category->meta_title ?: $category->name.' — Каталог')
            : 'Каталог';

        if ($category?->meta_description) {
            $metaDescription = Seo::metaDescription($category->meta_description);
        } elseif ($category) {
            $metaDescription = Seo::metaDescription(
                $category->description,
                'Каталог «'.$category->name.'» в интернет-магазине '.$storeName.'.',
            );
        } else {
            $metaDescription = Setting::get(
                'site_meta_description',
                'Каталог автозапчастей — '.$storeName.'.',
            );
        }

        $canonicalUrl = $this->categorySlug
            ? route('catalog', ['categorySlug' => $this->categorySlug])
            : route('catalog');

        return view('livewire.storefront.product-grid', [
            'products' => $this->products,
        ])->layout('layouts.storefront', [
            'title' => $titleSegment,
            'metaDescription' => $metaDescription,
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
