<div class="flex flex-col lg:flex-row gap-8">
    {{-- Сайдбар: категории и фильтры --}}
    <aside class="lg:w-64 shrink-0">
        <div class="bg-white rounded-lg border border-slate-200 p-4 sticky top-24">
            <h3 class="font-semibold text-slate-800 mb-3">Категории</h3>
            <ul class="space-y-1">
                <li>
                    <a href="{{ route('catalog') }}"
                       class="block py-1.5 px-2 rounded {{ !$categorySlug ? 'bg-slate-100 font-medium' : 'text-slate-600 hover:bg-slate-50' }}"
                    >Все товары</a>
                </li>
                @foreach($this->rootCategories as $root)
                    <li>
                        <a href="{{ route('catalog', ['categorySlug' => $root->slug]) }}"
                           class="block py-1.5 px-2 rounded {{ $categorySlug === $root->slug ? 'bg-slate-100 font-medium' : 'text-slate-600 hover:bg-slate-50' }}"
                        >{{ $root->name }}</a>
                        @if($root->children->isNotEmpty())
                            <ul class="ml-3 mt-1 space-y-1">
                                @foreach($root->children as $child)
                                    <li>
                                        <a href="{{ route('catalog', ['categorySlug' => $child->slug]) }}"
                                           class="block py-1 px-2 rounded text-sm {{ $categorySlug === $child->slug ? 'bg-slate-100 font-medium' : 'text-slate-600 hover:bg-slate-50' }}"
                                        >{{ $child->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if($this->brandsInCategory->isNotEmpty())
                <h3 class="font-semibold text-slate-800 mt-6 mb-3">Бренд</h3>
                <ul class="space-y-1">
                    <li>
                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                            <input type="radio" name="brandFilter" value="0"
                                   wire:model.live="brandId"
                                   class="rounded border-slate-300">
                            <span class="text-sm text-slate-700">Все бренды</span>
                        </label>
                    </li>
                    @foreach($this->brandsInCategory as $brand)
                        <li>
                            <label class="flex items-center gap-2 py-1 cursor-pointer">
                                <input type="radio" name="brandFilter" value="{{ $brand->id }}"
                                       wire:model.live="brandId"
                                       class="rounded border-slate-300">
                                <span class="text-sm text-slate-700">{{ $brand->name }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($brandId > 0 || $search !== '' || $sort !== 'name_asc')
                <button type="button" wire:click="clearFilters"
                        class="mt-4 text-sm text-slate-500 hover:text-slate-700 underline">
                    Сбросить фильтры
                </button>
            @endif
        </div>
    </aside>

    {{-- Контент: поиск, сортировка, сетка --}}
    <div class="flex-1 min-w-0">
        <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center mb-6">
            <div class="w-full sm:w-72">
                <input type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Поиск по названию или артикулу"
                       class="w-full rounded-lg border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <label for="sort" class="text-sm text-slate-600">Сортировка:</label>
                <select id="sort" wire:model.live="sort"
                        class="rounded-lg border-slate-300 shadow-sm text-sm">
                    <option value="name_asc">По названию (А–Я)</option>
                    <option value="name_desc">По названию (Я–А)</option>
                    <option value="price_asc">Сначала дешевле</option>
                    <option value="price_desc">Сначала дороже</option>
                    <option value="brand_asc">По бренду (А–Я)</option>
                    <option value="brand_desc">По бренду (Я–А)</option>
                    <option value="newest">Новинки</option>
                </select>
            </div>
        </div>

        @if($products->isEmpty())
            <p class="text-slate-600 py-12 text-center">В этой категории пока нет товаров.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($products as $product)
                    <a href="{{ route('product.show', $product) }}"
                       class="group bg-white rounded-lg border border-slate-200 overflow-hidden hover:border-slate-300 hover:shadow-md transition">
                        <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                            @if($product->mainImage)
                                <img src="{{ asset('storage/' . $product->mainImage->path) }}"
                                     alt="{{ $product->mainImage->alt ?? $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition">
                            @else
                                <span class="text-slate-400 text-sm">Нет фото</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-slate-500 mb-0.5">{{ $product->sku }}</p>
                            <h3 class="font-medium text-slate-800 group-hover:text-slate-600 line-clamp-2">{{ $product->name }}</h3>
                            <p class="mt-2 text-lg font-semibold text-slate-900">
                                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'EUR') }}
                            </p>
                            @if($product->in_stock)
                                <p class="mt-1 text-xs text-emerald-600">
                                    @if($product->total_stock < 10)
                                        В наличии ({{ $product->total_stock }})
                                    @else
                                        В наличии
                                    @endif
                                </p>
                            @else
                                <p class="mt-1 text-xs text-amber-600">Под заказ</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
