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

            <div class="mt-6">
                <h3 class="font-semibold text-slate-800 mb-3">Подбор по авто</h3>
                <div class="space-y-3">
                    <div>
                        <label for="vehicle-make" class="block text-sm text-slate-600 mb-1">Марка</label>
                        <select id="vehicle-make" wire:model.live="vehicleMake" class="w-full rounded-lg border-slate-300 shadow-sm text-sm">
                            <option value="">Все марки</option>
                            @foreach($this->vehicleMakes as $make)
                                <option value="{{ $make }}">{{ $make }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="vehicle-model" class="block text-sm text-slate-600 mb-1">Модель</label>
                        <select id="vehicle-model" wire:model.live="vehicleModel" class="w-full rounded-lg border-slate-300 shadow-sm text-sm" @disabled($vehicleMake === '')>
                            <option value="">Все модели</option>
                            @foreach($this->vehicleModels as $model)
                                <option value="{{ $model }}">{{ $model }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="vehicle-year" class="block text-sm text-slate-600 mb-1">Год</label>
                        <select id="vehicle-year" wire:model.live="vehicleYear" class="w-full rounded-lg border-slate-300 shadow-sm text-sm" @disabled($vehicleModel === '')>
                            <option value="0">Любой год</option>
                            @foreach($this->vehicleYears as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold text-slate-800 mb-3">Цена</h3>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number"
                           min="0"
                           step="0.01"
                           wire:model.live.debounce.400ms="priceFrom"
                           placeholder="От"
                           class="w-full rounded-lg border-slate-300 shadow-sm text-sm">
                    <input type="number"
                           min="0"
                           step="0.01"
                           wire:model.live.debounce.400ms="priceTo"
                           placeholder="До"
                           class="w-full rounded-lg border-slate-300 shadow-sm text-sm">
                </div>
            </div>

            <div class="mt-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="inStockOnly" class="rounded border-slate-300">
                    <span class="text-sm text-slate-700">Только в наличии</span>
                </label>
            </div>

            <h3 class="font-semibold text-slate-800 mt-6 mb-3">Бренд</h3>
            @if($this->brandsInCategory->isNotEmpty())
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
            @else
                <p class="text-sm text-slate-500">Для текущего набора фильтров доступных брендов нет.</p>
            @endif

            @if($brandId > 0 || $search !== '' || $sort !== 'name_asc' || $vehicleMake !== '' || $vehicleModel !== '' || $vehicleYear > 0 || $priceFrom !== '' || $priceTo !== '' || $inStockOnly)
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
                       placeholder="Поиск по названию, SKU, OEM или аналогу"
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

        @if($brandId > 0 || $vehicleMake !== '' || $vehicleModel !== '' || $vehicleYear > 0 || $priceFrom !== '' || $priceTo !== '' || $inStockOnly)
            <div class="mb-4 flex flex-wrap gap-2 text-sm">
                @if($brandId > 0)
                    @php $selectedBrand = $this->brandsInCategory->firstWhere('id', $brandId); @endphp
                    @if($selectedBrand)
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $selectedBrand->name }}</span>
                    @endif
                @endif
                @if($vehicleMake !== '')
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $vehicleMake }}</span>
                @endif
                @if($vehicleModel !== '')
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $vehicleModel }}</span>
                @endif
                @if($vehicleYear > 0)
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">{{ $vehicleYear }}</span>
                @endif
                @if($priceFrom !== '' || $priceTo !== '')
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">
                        Цена:
                        {{ $priceFrom !== '' ? ' от ' . $priceFrom : '' }}
                        {{ $priceTo !== '' ? ' до ' . $priceTo : '' }}
                    </span>
                @endif
                @if($inStockOnly)
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-slate-700">Только в наличии</span>
                @endif
            </div>
        @endif

        @if($products->isEmpty())
            <p class="text-slate-600 py-12 text-center">По выбранным фильтрам товары не найдены.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($products as $product)
                    <a href="{{ route('product.show', $product) }}"
                       class="group bg-white rounded-lg border border-slate-200 overflow-hidden hover:border-slate-300 hover:shadow-md transition">
                        <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                            @if($product->mainImage?->storage_url)
                                <img src="{{ $product->mainImage->storage_url }}"
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
                                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
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
                            @if($this->selectedVehicleLabel)
                                <p class="mt-1 text-xs text-indigo-600">
                                    Подходит для {{ $this->selectedVehicleLabel }}
                                </p>
                            @endif

                            <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 p-3 space-y-1.5">
                                @if($product->oemNumbers->isNotEmpty())
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">OEM:</span>
                                        <span class="font-mono">{{ $product->oemNumbers->take(2)->pluck('oem_number')->join(', ') }}</span>
                                    </p>
                                @endif

                                @if($product->crossNumbers->isNotEmpty())
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">Аналоги:</span>
                                        <span class="font-mono">{{ $product->crossNumbers->take(2)->pluck('cross_number')->join(', ') }}</span>
                                    </p>
                                @endif

                                @if(!$this->selectedVehicleLabel && $product->vehicles->isNotEmpty())
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">Совместимость:</span>
                                        {{ $product->vehicles->take(2)->map(fn ($vehicle) => $vehicle->make . ' ' . $vehicle->model)->join(', ') }}
                                    </p>
                                @endif
                            </div>
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
