<div class="relative">
    @php
        $catalogLink = function (array $params = []) use ($vehiclePageContext, $vehicleId) {
            if ($vehiclePageContext && $vehicleId > 0) {
                return route('vehicle.parts', array_merge(['vehicle' => $vehicleId], $params));
            }

            return $params === [] ? route('catalog') : route('catalog', $params);
        };
    @endphp
    {{-- Ожидание ответа сервера при смене фильтров, сортировки, поиска или страницы --}}
    <div
        wire:loading.delay.shortest
        class="fixed inset-0 z-[200] bg-stone-900/20 backdrop-blur-[2px]"
        role="status"
        aria-live="polite"
        aria-busy="true"
    >
        <div class="flex h-full w-full items-center justify-center p-4">
            <div class="w-full max-w-xs rounded-2xl border border-orange-100/90 bg-white px-6 py-5 shadow-xl shadow-orange-950/10 sm:max-w-sm">
                <div class="storefront-filter-loading-track">
                    <div class="storefront-filter-loading-bar"></div>
                </div>
                <p class="mt-4 text-center text-sm font-medium text-stone-700">Подбираем товары…</p>
            </div>
        </div>
    </div>

    <nav class="text-sm text-slate-500 mb-6 -mx-1 px-1 overflow-x-auto whitespace-nowrap sm:whitespace-normal sm:overflow-visible" aria-label="Навигация">
        <a href="{{ route('home') }}" class="font-medium text-slate-700 hover:text-slate-900">Главная</a>
        @if($vehiclePageContext && $this->selectedVehicleLabel)
            <span class="mx-1">/</span>
            <span class="text-slate-700">{{ $this->selectedVehicleLabel }}</span>
        @endif
        <span class="mx-1">/</span>
        <a href="{{ $catalogLink() }}" class="font-medium text-slate-700 hover:text-slate-900">Каталог</a>
        @foreach($this->categoryBreadcrumbChain as $cat)
            <span class="mx-1">/</span>
            @if($loop->last)
                <span class="text-slate-700 font-medium">{{ $cat->name }}</span>
            @else
                <a href="{{ $catalogLink(['categorySlug' => $cat->slug]) }}" class="hover:text-slate-700">{{ $cat->name }}</a>
            @endif
        @endforeach
    </nav>

    <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
    {{-- Сайдбар: на мобильных под списком товаров (order), на lg — слева --}}
    <aside class="order-2 shrink-0 lg:order-1 lg:w-64">
        <div class="space-y-4 rounded-xl border border-orange-100/90 bg-white/90 p-3 shadow-sm shadow-orange-950/5 backdrop-blur-sm lg:sticky lg:top-24">
            @if(! $vehiclePageContext)
            <details class="sidebar-accordion rounded-lg border border-orange-100/80 bg-orange-50/40 shadow-sm">
                <summary class="flex w-full cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-3 text-left transition hover:bg-orange-50/80 [&::-webkit-details-marker]:hidden">
                    <span class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <span class="text-base font-semibold text-stone-900">Категории</span>
                        <span class="text-lg font-bold tabular-nums text-orange-700">({{ $this->categoryAccordionItemCount }})</span>
                    </span>
                    <svg class="sidebar-accordion-chevron h-5 w-5 shrink-0 text-stone-500 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </summary>
                <div class="rounded-b-lg border-t border-orange-100 bg-white px-2 py-2.5">
                    <ul class="space-y-1">
                        <li>
                            <a href="{{ $catalogLink() }}"
                               class="block rounded-md px-2 py-2 text-base leading-snug {{ !$categorySlug ? 'bg-orange-50 font-medium text-stone-900' : 'text-stone-600 hover:bg-orange-50/60' }}"
                            >Все товары</a>
                        </li>
                        @foreach($this->rootCategories as $root)
                            <li>
                                @if($root->children->isNotEmpty())
                                    @php
                                        $openBranch = $categorySlug !== null && $categorySlug !== ''
                                            && (
                                                $categorySlug === $root->slug
                                                || $root->children->contains(fn ($c) => $c->slug === $categorySlug)
                                            );
                                    @endphp
                                    <details class="sidebar-accordion sidebar-category-branch rounded-md" @if($openBranch) open @endif>
                                        <summary class="flex w-full cursor-pointer list-none items-stretch gap-0.5 rounded-md text-base leading-snug text-stone-600 hover:bg-orange-50/60 [&::-webkit-details-marker]:hidden">
                                            <a href="{{ $catalogLink(['categorySlug' => $root->slug]) }}"
                                               class="min-w-0 flex-1 rounded-l-md px-2 py-2 text-left {{ $categorySlug === $root->slug ? 'bg-orange-50 font-medium text-stone-900' : 'text-stone-600 hover:bg-orange-50/60' }}"
                                               onclick="event.stopPropagation()">{{ $root->name }}</a>
                                            <span class="flex shrink-0 items-center px-1.5 text-slate-500" aria-hidden="true">
                                                <svg class="sidebar-accordion-chevron h-4 w-4 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </span>
                                        </summary>
                                        <ul class="ml-2 mt-1 space-y-1 border-l border-orange-100 pl-2 pb-1">
                                            @foreach($root->children as $child)
                                                <li>
                                                    <a href="{{ $catalogLink(['categorySlug' => $child->slug]) }}"
                                                       class="block rounded-md px-2 py-1.5 text-base leading-snug {{ $categorySlug === $child->slug ? 'bg-orange-50 font-medium text-stone-900' : 'text-stone-600 hover:bg-orange-50/60' }}"
                                                    >{{ $child->name }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <a href="{{ $catalogLink(['categorySlug' => $root->slug]) }}"
                                       class="block rounded-md px-2 py-2 text-base leading-snug {{ $categorySlug === $root->slug ? 'bg-orange-50 font-medium text-stone-900' : 'text-stone-600 hover:bg-orange-50/60' }}"
                                    >{{ $root->name }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
            @endif

            <div>
                <h3 class="font-semibold text-slate-800 mb-3">Подбор по авто</h3>
                @if($vehiclePageContext && $this->selectedVehicleLabel)
                    <p class="rounded-lg border border-orange-100/90 bg-orange-50/50 px-3 py-2 text-sm leading-snug text-stone-800">
                        {{ $this->selectedVehicleLabel }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">
                        <a href="{{ route('home') }}" class="font-medium text-orange-800 underline decoration-orange-200 underline-offset-2 hover:decoration-orange-500">Выбрать другое авто</a>
                    </p>
                @else
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
                                    <option value="{{ $model }}">{{ \App\Models\Vehicle::finderModelLabelWithoutTecdocCodeSuffix($model) }}</option>
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
                @endif
            </div>

            <div>
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

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="inStockOnly" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500">
                    <span class="text-sm text-slate-700">Только в наличии</span>
                </label>
            </div>

            @if($this->brandsInCategory->isNotEmpty())
            <details class="sidebar-accordion rounded-lg border border-orange-100/80 bg-orange-50/40 shadow-sm">
                <summary class="flex w-full cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-3 text-left transition hover:bg-orange-50/80 [&::-webkit-details-marker]:hidden">
                    <span class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <span class="text-base font-semibold text-stone-900">Бренд</span>
                        <span class="text-lg font-bold tabular-nums text-orange-700">({{ $this->brandAccordionItemCount }})</span>
                    </span>
                    <svg class="sidebar-accordion-chevron h-5 w-5 shrink-0 text-slate-500 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </summary>
                <div class="max-h-64 overflow-y-auto overscroll-contain rounded-b-lg border-t border-orange-100 bg-white px-2 py-2.5">
                    <ul class="space-y-1">
                        <li>
                            <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-orange-50/60">
                                <input type="radio" name="brandFilter" value="0"
                                       wire:model.live="brandId"
                                       class="h-4 w-4 shrink-0 border-stone-300 text-orange-600 focus:ring-orange-500">
                                <span class="text-base leading-snug text-slate-700">Все бренды</span>
                            </label>
                        </li>
                        @foreach($this->brandsInCategory as $brand)
                            <li>
                            <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-orange-50/60">
                                <input type="radio" name="brandFilter" value="{{ $brand->id }}"
                                           wire:model.live="brandId"
                                           class="h-4 w-4 shrink-0 border-stone-300 text-orange-600 focus:ring-orange-500">
                                    <span class="text-base leading-snug text-slate-700">{{ $brand->name }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
            @endif

            @if($brandId > 0 || $search !== '' || $sort !== 'name_asc' || $vehicleMake !== '' || $vehicleModel !== '' || $vehicleYear > 0 || $vehicleId > 0 || $priceFrom !== '' || $priceTo !== '' || $inStockOnly)
                <button type="button" wire:click="clearFilters"
                        class="mt-4 text-sm text-orange-700/80 underline transition hover:text-orange-800">
                    Сбросить фильтры
                </button>
            @endif
        </div>
    </aside>

    {{-- Контент: поиск, сортировка, сетка — на мобильных сверху --}}
    <div class="order-1 min-w-0 flex-1 lg:order-2">
        @if($vehiclePageContext && $this->selectedVehicleLabel)
            <h1 class="mb-4 text-xl font-bold text-stone-900 sm:text-2xl">Запчасти для {{ $this->selectedVehicleLabel }}</h1>
        @endif
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="w-full min-w-0 sm:max-w-md sm:flex-1 lg:max-w-xs">
                <input type="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Поиск по названию, SKU, OEM или аналогу"
                       class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div class="flex w-full min-w-0 flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end md:w-auto">
                <div class="flex min-w-0 flex-1 items-center gap-2 sm:flex-initial">
                    <label for="sort" class="shrink-0 text-sm text-slate-600">Сортировка:</label>
                    <select id="sort" wire:model.live="sort"
                            class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm shadow-sm sm:min-w-[11rem] sm:flex-initial">
                        <option value="name_asc">По названию (А–Я)</option>
                        <option value="name_desc">По названию (Я–А)</option>
                        <option value="price_asc">Сначала дешевле</option>
                        <option value="price_desc">Сначала дороже</option>
                        <option value="brand_asc">По бренду (А–Я)</option>
                        <option value="brand_desc">По бренду (Я–А)</option>
                        <option value="newest">Новинки</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label for="per-page" class="shrink-0 whitespace-nowrap text-sm text-slate-600">На стр.:</label>
                    <select id="per-page" wire:model.live="perPage"
                            class="min-w-[5.5rem] rounded-lg border-slate-300 text-sm shadow-sm">
                        @foreach($this->perPageOptions as $n)
                            <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($brandId > 0 || $vehicleMake !== '' || $vehicleModel !== '' || $vehicleYear > 0 || $vehicleId > 0 || $priceFrom !== '' || $priceTo !== '' || $inStockOnly)
            <div class="mb-4 flex flex-wrap gap-2 text-sm">
                @if($brandId > 0)
                    @php $selectedBrand = $this->brandsInCategory->firstWhere('id', $brandId); @endphp
                    @if($selectedBrand)
                        <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-sm font-medium text-stone-800 ring-1 ring-orange-100/80">{{ $selectedBrand->name }}</span>
                    @endif
                @endif
                @if($vehicleMake !== '')
                    <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-sm font-medium text-stone-800 ring-1 ring-orange-100/80">{{ $vehicleMake }}</span>
                @endif
                @if($vehicleModel !== '')
                    <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-sm font-medium text-stone-800 ring-1 ring-orange-100/80">{{ \App\Models\Vehicle::finderModelLabelWithoutTecdocCodeSuffix($vehicleModel) }}</span>
                @endif
                @if($vehicleYear > 0)
                    <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-sm font-medium text-stone-800 ring-1 ring-orange-100/80">{{ $vehicleYear }}</span>
                @endif
                @if($priceFrom !== '' || $priceTo !== '')
                    <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-sm font-medium text-stone-800 ring-1 ring-orange-100/80">
                        Цена:
                        {{ $priceFrom !== '' ? ' от ' . $priceFrom : '' }}
                        {{ $priceTo !== '' ? ' до ' . $priceTo : '' }}
                    </span>
                @endif
                @if($inStockOnly)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200/80">Только в наличии</span>
                @endif
            </div>
        @endif

        @if($products->isEmpty())
            <p class="text-slate-600 py-12 text-center">По выбранным фильтрам товары не найдены.</p>
        @else
            @php
                $pgVehicleQuery = array_filter([
                    'vehicleId' => $vehicleId > 0 ? $vehicleId : null,
                    'vehicleMake' => $vehicleMake !== '' ? $vehicleMake : null,
                    'vehicleModel' => $vehicleModel !== '' ? $vehicleModel : null,
                    'vehicleYear' => $vehicleYear > 0 ? (string) $vehicleYear : null,
                ], static fn ($v) => $v !== null && $v !== '');
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($products as $product)
                    <a href="{{ route('product.show', $product) }}{{ $pgVehicleQuery === [] ? '' : '?'.http_build_query($pgVehicleQuery) }}"
                       class="card-store-product group">
                        <div class="flex aspect-square items-center justify-center overflow-hidden bg-stone-100">
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
                            <h3 class="line-clamp-2 font-medium text-stone-900 transition group-hover:text-orange-800">{{ $product->name }}</h3>
                            <p class="mt-2 text-xl font-bold text-orange-800">
                                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
                            </p>
                            @if($product->in_stock)
                                <p class="mt-1 text-xs font-semibold text-emerald-700">
                                    @if($product->total_stock < 10)
                                        В наличии ({{ $product->total_stock }})
                                    @else
                                        В наличии
                                    @endif
                                </p>
                            @else
                                <p class="mt-1 text-xs font-semibold text-amber-700">Под заказ</p>
                            @endif
                            @if($this->selectedVehicleLabel && (! $vehiclePageContext || $vehicleId <= 0 || $product->vehicles->contains('id', (int) $vehicleId)))
                                <p class="mt-1 text-xs font-medium text-orange-700">
                                    Подходит для {{ $this->selectedVehicleLabel }}
                                </p>
                            @endif

                            <div class="mt-3 space-y-1.5 rounded-xl border border-orange-100/80 bg-orange-50/40 p-3">
                                @if($product->oemNumbers->isNotEmpty())
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">OEM:</span>
                                        <span class="font-mono">{{ $product->oemNumbers->take(2)->pluck('oem_number')->join(', ') }}</span>
                                    </p>
                                @endif

                                @if($product->crossNumbers->isNotEmpty())
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">Аналоги:</span>
                                        <span class="font-mono">{{ $product->crossNumbers->take(2)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}</span>
                                    </p>
                                @endif

                                @if(!$this->selectedVehicleLabel && $product->vehicles->isNotEmpty())
                                    @php
                                        $compatLines = $product->compatibilityLabelsForStorefrontCard(2);
                                    @endphp
                                    @if($compatLines !== [])
                                        <p class="text-xs text-slate-600">
                                            <span class="font-medium text-slate-700">Совместимость:</span>
                                            {{ implode(', ', $compatLines) }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8 overflow-x-auto pb-2 [-webkit-overflow-scrolling:touch]">
                {{ $products->links() }}
            </div>
        @endif
    </div>
    </div>
</div>
