<?php

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vehicle;
use App\Support\ProductNameVehicleExtractor;
use App\Support\StorefrontVehicleFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class HomePartFinder extends Component
{
    public string $vehicleMake = '';

    public string $vehicleModel = '';

    public int $vehicleYear = 0;

    public int $categoryId = 0;

    public int $productId = 0;

    public int $vehicleId = 0;

    /** Поиск из шапки (показываем блок результатов на главной). */
    public string $search = '';

    protected $queryString = [
        'vehicleMake' => ['except' => ''],
        'vehicleModel' => ['except' => ''],
        'vehicleYear' => ['except' => 0],
        'categoryId' => ['except' => 0],
        'productId' => ['except' => 0],
        'vehicleId' => ['except' => 0],
        'search' => ['except' => ''],
    ];

    protected function casts(): array
    {
        return [
            'vehicleYear' => 'integer',
            'categoryId' => 'integer',
            'productId' => 'integer',
            'vehicleId' => 'integer',
        ];
    }

    public function mount(): void
    {
        if ($this->vehicleId > 0) {
            $vehicle = Vehicle::query()->find($this->vehicleId);
            if ($vehicle) {
                $this->vehicleMake = (string) $vehicle->make;
                $this->vehicleModel = (string) $vehicle->model;
                if ($vehicle->year_from !== null && $vehicle->year_to !== null) {
                    $this->vehicleYear = (int) floor(($vehicle->year_from + $vehicle->year_to) / 2);
                } elseif ($vehicle->year_from !== null) {
                    $this->vehicleYear = (int) $vehicle->year_from;
                } elseif ($vehicle->year_to !== null) {
                    $this->vehicleYear = (int) $vehicle->year_to;
                }
            } else {
                $this->vehicleId = 0;
            }
        }

        $this->syncSelections();
    }

    public function getVehicleMakesProperty(): Collection
    {
        return Vehicle::query()
            ->whereHas('products', fn (Builder $q) => $q->where('is_active', true))
            ->select('make')
            ->distinct()
            ->orderBy('make')
            ->pluck('make');
    }

    public function getVehicleModelsProperty(): Collection
    {
        if ($this->vehicleMake === '') {
            return collect();
        }

        $make = trim($this->vehicleMake);
        $makeLower = mb_strtolower($make);

        $fromDb = Vehicle::query()
            ->whereRaw('LOWER(make) = ?', [$makeLower])
            ->whereHas('products', fn (Builder $q) => $q->active())
            ->select('model')
            ->distinct()
            ->pluck('model')
            ->map(fn ($m) => trim((string) $m))
            ->filter(fn (string $m) => $m !== '' && ! ProductNameVehicleExtractor::isPlaceholderVehicleModel($m))
            ->unique()
            ->values();

        $fromNames = Product::query()
            ->active()
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

    public function getVehicleYearsProperty(): Collection
    {
        if ($this->vehicleMake === '' || $this->vehicleModel === '') {
            return collect();
        }

        $years = Vehicle::query()
            ->whereRaw('LOWER(make) = ?', [mb_strtolower(trim($this->vehicleMake))])
            ->whereHas('products', fn (Builder $q) => $q->active())
            ->get(['year_from', 'year_to'])
            ->flatMap(function (Vehicle $vehicle) {
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

    public function getCategoriesForVehicleProperty(): Collection
    {
        if ($this->vehicleMake === '' || $this->vehicleModel === '' || $this->vehicleYear <= 0) {
            return collect();
        }

        $like = $this->hiddenCategorySlugLike();

        $q = Product::query()->active();
        StorefrontVehicleFilter::constrainProductsByVehicle($q, $this->vehicleMake, $this->vehicleModel, $this->vehicleYear);
        $ids = $q->clone()->distinct()->pluck('category_id')->filter()->values();

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
        if ($this->categoryId <= 0 || $this->vehicleMake === '' || $this->vehicleModel === '' || $this->vehicleYear <= 0) {
            return collect();
        }

        $q = Product::query()
            ->active()
            ->where('category_id', $this->categoryId);

        StorefrontVehicleFilter::constrainProductsByVehicle($q, $this->vehicleMake, $this->vehicleModel, $this->vehicleYear);

        return $q->with(['category', 'brand', 'images', 'stocks', 'crossNumbers', 'vehicles'])
            ->orderBy('name')
            ->get();
    }

    public function getSelectedProductProperty(): ?Product
    {
        if ($this->productId <= 0 || $this->categoryId <= 0) {
            return null;
        }

        $q = Product::query()
            ->active()
            ->whereKey($this->productId)
            ->where('category_id', $this->categoryId);

        StorefrontVehicleFilter::constrainProductsByVehicle($q, $this->vehicleMake, $this->vehicleModel, $this->vehicleYear);

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
     * @return \Illuminate\Support\Collection<int, object{cross: \App\Models\ProductCrossNumber, linked: \App\Models\Product}>
     */
    public function getCrossAnalogRowsProperty(): Collection
    {
        $product = $this->selectedProduct;
        if ($product === null) {
            return collect();
        }

        return $product->crossNumbersWithLinkedProducts()->map(function (object $item) {
            $item->linked->loadMissing(['category', 'brand', 'images', 'stocks', 'crossNumbers', 'vehicles']);

            return $item;
        });
    }

    public function updatedVehicleMake(): void
    {
        $this->vehicleId = 0;
        $this->vehicleModel = '';
        $this->vehicleYear = 0;
        $this->categoryId = 0;
        $this->productId = 0;
        $this->syncSelections();
    }

    public function updatedVehicleModel(): void
    {
        $this->vehicleId = 0;
        $this->vehicleYear = 0;
        $this->categoryId = 0;
        $this->productId = 0;
        $this->syncSelections();
    }

    public function updatedVehicleYear(): void
    {
        $this->vehicleId = 0;
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
        $this->vehicleModel = '';
        $this->vehicleYear = 0;
        $this->categoryId = 0;
        $this->productId = 0;
        $this->vehicleId = 0;
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

        if ($this->vehicleModel !== '' && ! $this->vehicleModels->contains($this->vehicleModel)) {
            $this->vehicleModel = '';
            $this->vehicleYear = 0;
            $this->categoryId = 0;
            $this->productId = 0;
        }

        if ($this->vehicleYear > 0 && ! $this->vehicleYears->contains($this->vehicleYear)) {
            $this->vehicleYear = 0;
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
}
