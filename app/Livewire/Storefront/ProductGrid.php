<?php

namespace App\Livewire\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
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

    protected $queryString = [
        'categorySlug' => ['except' => ''],
        'brandId'      => ['except' => 0],
        'sort'         => ['except' => 'name_asc'],
        'search'       => ['except' => ''],
    ];

    /** Значение с формы и из URL всегда приводим к int */
    protected function casts(): array
    {
        return ['brandId' => 'integer'];
    }

    public function mount(?string $categorySlug = null): void
    {
        $this->categorySlug = $categorySlug;
    }

    public function getCategoryProperty(): ?Category
    {
        if (!$this->categorySlug) {
            return null;
        }

        return Category::where('slug', $this->categorySlug)->active()->first();
    }

    public function getRootCategoriesProperty()
    {
        return Category::active()->roots()->with('children')->orderBy('sort')->get();
    }

    public function getBrandsInCategoryProperty()
    {
        $query = Brand::active()->whereHas('products', function (Builder $q) {
            $q->active();
            if ($this->category) {
                $q->where('category_id', $this->category->id);
            }
        })->orderBy('name');

        return $query->get();
    }

    public function getProductsProperty()
    {
        $brandId = (int) $this->brandId;

        $query = Product::active()
            ->with(['category', 'brand', 'images', 'stocks'])
            ->when($this->category, fn (Builder $q) => $q->where('category_id', $this->category->id))
            ->when($brandId > 0, fn (Builder $q) => $q->where('brand_id', $brandId))
            ->when($this->search !== '', function (Builder $q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            });

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

    public function updatedCategorySlug(): void
    {
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->brandId = 0;
        $this->search = '';
        $this->sort = 'name_asc';
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.storefront.product-grid', [
            'products' => $this->products,
        ])->layout('layouts.storefront', [
            'title' => $this->category ? $this->category->name . ' — Каталог' : 'Каталог',
        ]);
    }
}
