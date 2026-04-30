<div class="relative w-full">
    <div
        wire:loading.delay.shortest
        wire:target="decodeVin,clearSelection,vehicleMake,vehicleId,categoryId,productId"
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
                <p class="mt-4 text-center text-sm font-medium text-stone-700">Загружаем подбор…</p>
            </div>
        </div>
    </div>

    <div
        wire:loading.delay.shortest
        wire:target="enterVinCategoryBranch,vinCategoryNavigateUp,vinCategoryNavigateRoot,selectVinCatalogCategory"
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
                <p class="mt-4 text-center text-sm font-medium text-stone-700">Загружаем…</p>
            </div>
        </div>
    </div>

    <fieldset class="mx-auto mb-5 max-w-5xl border-0 p-0">
        <legend class="mb-1.5 block w-full text-center text-xs font-medium text-stone-600 sm:text-left sm:text-[13px]">Поиск по номеру</legend>
        @livewire('storefront.header-search', ['variant' => 'hero'], key('storefront-home-search'))
    </fieldset>

    <fieldset class="mx-auto mb-5 max-w-5xl border-0 p-0">
        <legend class="mb-1.5 block w-full text-center text-xs font-medium text-stone-600 sm:text-left sm:text-[13px]">Поиск по VIN</legend>
        <form wire:submit.prevent="decodeVin" class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
            <input
                type="search"
                wire:model.defer="vin"
                maxlength="32"
                placeholder="Введите VIN (17 символов)"
                class="w-full min-w-0 rounded-lg border border-orange-200/90 bg-white px-3 py-2.5 text-sm text-stone-900 shadow-sm ring-1 ring-orange-100/80 placeholder:text-stone-400 focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/30"
            >
            <button
                type="submit"
                class="rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500/40"
            >
                Проверить VIN
            </button>
        </form>
        @error('vin')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror

        @if($vinDecodeResult !== null)
            <div class="mt-3 rounded-xl border border-orange-100/90 bg-white p-3 shadow-sm">
                <p class="text-sm font-semibold {{ $vinDecodeResult['success'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $vinDecodeResult['success'] ? 'VIN проверен' : 'VIN проверен с предупреждениями' }}
                </p>
                @if(!empty($vinDecodeResult['provider_used']))
                    <p class="mt-1 text-xs text-stone-600">
                        Источник: {{ $vinDecodeResult['provider_used'] }}
                    </p>
                @endif
                @if(trim($vinDecodeMessage) !== '')
                    <p class="mt-1 text-xs text-stone-600">{{ $vinDecodeMessage }}</p>
                @endif
                <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-sm text-stone-700 sm:grid-cols-2">
                    <p><span class="font-medium text-stone-900">Марка:</span> {{ $vinDecodeResult['make'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Модель:</span> {{ $vinDecodeResult['model'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Год:</span> {{ $vinDecodeResult['model_year'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Кузов:</span> {{ $vinDecodeResult['body_class'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Двигатель:</span> {{ $vinDecodeResult['engine'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Топливо:</span> {{ $vinDecodeResult['fuel_type'] ?: '—' }}</p>
                    <p><span class="font-medium text-stone-900">Трансмиссия:</span> {{ $vinDecodeResult['transmission'] ?: '—' }}</p>
                </div>

                <div class="mt-3 border-t border-orange-100 pt-3">
                    <p class="text-sm font-medium text-stone-900">Категории запчастей (RapidAPI)</p>
                    <p class="mt-0.5 text-xs text-stone-500">
                        @if(!empty($this->vinCategoryRowsCacheToken))
                            Разделы для этой модификации: сначала главные категории; по клику — только подкатегории, доступные для выбранного авто. В конце ветки загрузим артикулы каталога.
                        @else
                            Нажмите категорию — покажем артикулы из каталога.
                        @endif
                    </p>
                    @if(!empty($this->vinCategoryRowsCacheToken))
                        <div class="mt-4 space-y-4" wire:key="vin-cat-tree-{{ $vinCatalogVehicleId ?? 0 }}">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-stone-600 sm:text-xs">
                                <button
                                    type="button"
                                    wire:click="vinCategoryNavigateRoot"
                                    wire:loading.attr="disabled"
                                    wire:target="vinCategoryNavigateRoot,vinCategoryNavigateUp,enterVinCategoryBranch,selectVinCatalogCategory"
                                    @disabled(count($this->vinCategoryPath) === 0)
                                    class="rounded-lg border border-orange-200/80 bg-white px-2.5 py-1 font-medium text-orange-900 transition hover:border-orange-400 hover:bg-orange-50/90 disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    Все разделы
                                </button>
                                @if(count($this->vinCategoryPath) > 0)
                                    <button
                                        type="button"
                                        wire:click="vinCategoryNavigateUp"
                                        wire:loading.attr="disabled"
                                        wire:target="vinCategoryNavigateRoot,vinCategoryNavigateUp,enterVinCategoryBranch,selectVinCatalogCategory"
                                        class="rounded-lg border border-stone-200 bg-white px-2.5 py-1 font-medium text-stone-700 transition hover:bg-stone-50"
                                    >
                                        Назад
                                    </button>
                                    <span class="hidden text-stone-300 sm:inline" aria-hidden="true">|</span>
                                    <nav class="flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5 text-stone-700" aria-label="Путь по категориям">
                                        @foreach($this->vinCategoryPath as $crumb)
                                            <span class="max-w-[220px] truncate font-medium text-stone-900" title="{{ $crumb['name'] ?? '' }}">{{ $crumb['name'] ?? '—' }}</span>
                                            @if(!$loop->last)
                                                <span class="shrink-0 text-stone-400" aria-hidden="true">›</span>
                                            @endif
                                        @endforeach
                                    </nav>
                                @endif
                            </div>
                            @if(trim($vinCategoryNavMessage) !== '')
                                <p class="text-xs text-amber-800">{{ $vinCategoryNavMessage }}</p>
                            @endif

                            <div
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                wire:loading.class="opacity-60"
                                wire:target="enterVinCategoryBranch,selectVinCatalogCategory,vinCategoryNavigateUp,vinCategoryNavigateRoot"
                            >
                                @foreach($this->vinCategoryCurrentNodes as $node)
                                    @php
                                        $nid = isset($node['id']) && is_numeric($node['id']) ? (int) $node['id'] : 0;
                                        $nname = isset($node['name']) ? trim((string) $node['name']) : '';
                                        $hasChildren = !empty($node['has_children']);
                                    @endphp
                                    @if($nid > 0 && $nname !== '')
                                        <button
                                            type="button"
                                            wire:key="vin-cat-node-{{ $vinCatalogVehicleId ?? 0 }}-{{ $nid }}-{{ $loop->index }}"
                                            wire:click="enterVinCategoryBranch({{ $nid }})"
                                            wire:loading.attr="disabled"
                                            wire:target="enterVinCategoryBranch,selectVinCatalogCategory,vinCategoryNavigateUp,vinCategoryNavigateRoot"
                                            class="group flex min-h-[5.5rem] flex-col justify-between rounded-2xl border border-orange-100/90 bg-gradient-to-br from-white to-orange-50/40 p-4 text-left shadow-sm shadow-orange-950/5 transition hover:border-orange-300/90 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-orange-500/35 disabled:pointer-events-none disabled:opacity-60"
                                        >
                                            <span class="text-sm font-semibold leading-snug text-stone-900 group-hover:text-orange-950">{{ $nname }}</span>
                                            <span class="mt-3 flex items-center justify-between gap-2 text-[11px] font-medium uppercase tracking-wide text-stone-500 group-hover:text-orange-800/90">
                                                @if($hasChildren)
                                                    <span>Подразделы</span>
                                                    <span class="text-orange-600" aria-hidden="true">→</span>
                                                @else
                                                    <span>Артикулы каталога</span>
                                                    <span class="text-orange-600" aria-hidden="true">↧</span>
                                                @endif
                                            </span>
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @elseif($vinCategories !== [])
                        <div class="mt-2 flex flex-wrap gap-2" wire:key="vin-cat-pills-{{ $vinCatalogVehicleId ?? 0 }}">
                            @foreach($vinCategories as $cat)
                                @php
                                    $cid = isset($cat['id']) && is_numeric($cat['id']) ? (int) $cat['id'] : null;
                                    $isSelected = $cid !== null && $vinSelectedCatalogCategoryId === $cid;
                                @endphp
                                @if($cid !== null && $cid > 0)
                                    <button
                                        type="button"
                                        wire:click="selectVinCatalogCategory({{ $cid }})"
                                        wire:loading.attr="disabled"
                                        wire:target="selectVinCatalogCategory"
                                        class="rounded-full border px-2.5 py-1 text-xs transition focus:outline-none focus:ring-2 focus:ring-orange-500/40 disabled:opacity-60 {{ $isSelected ? 'border-orange-600 bg-orange-100 font-semibold text-orange-950' : 'border-orange-200 bg-orange-50 text-orange-900 hover:border-orange-400' }}"
                                    >
                                        {{ $cat['name'] ?? '—' }}
                                    </button>
                                @else
                                    <span class="rounded-full border border-stone-200 bg-stone-50 px-2.5 py-1 text-xs text-stone-600" title="Нет id категории в ответе API">
                                        {{ $cat['name'] ?? '—' }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @elseif(trim($vinCategoriesMessage) !== '')
                        <p class="mt-1 text-xs text-stone-600">{{ $vinCategoriesMessage }}</p>
                    @else
                        <p class="mt-1 text-xs text-stone-600">Категории пока не найдены.</p>
                    @endif

                    @if($vinSelectedCatalogCategoryId !== null && (!empty($this->vinCategoryRowsCacheToken) || $vinCategories !== []))
                        <div class="mt-3 rounded-lg border border-stone-200/90 bg-stone-50/80 p-3">
                            <p class="text-xs font-medium text-stone-800">Артикулы каталога</p>
                            @if(trim($vinCatalogArticlesMessage) !== '' && $vinCatalogArticles === [])
                                <p class="mt-1 text-xs text-stone-600">{{ $vinCatalogArticlesMessage }}</p>
                            @elseif($vinCatalogArticles !== [])
                                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    @foreach($vinCatalogArticles as $art)
                                        @php
                                            $img = isset($art['imageUrl']) && is_string($art['imageUrl']) ? trim($art['imageUrl']) : '';
                                            $imgOk = $img !== '' && preg_match('#^https?://#i', $img) === 1;
                                            $details = isset($art['details']) && is_array($art['details']) ? $art['details'] : [];
                                        @endphp
                                        <article class="flex gap-3 rounded-xl border border-stone-200/90 bg-white p-3 text-xs text-stone-800 shadow-sm">
                                            @if($imgOk)
                                                <div class="shrink-0">
                                                    <img
                                                        src="{{ $img }}"
                                                        alt=""
                                                        class="h-20 w-20 rounded-lg bg-stone-50 object-contain sm:h-24 sm:w-24"
                                                        loading="lazy"
                                                        decoding="async"
                                                    />
                                                </div>
                                            @endif
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <p class="text-sm font-semibold text-stone-900">{{ $art['supplierName'] ?? '—' }}</p>
                                                <p class="text-stone-700">{{ $art['name'] ?? '—' }}</p>
                                                @if(trim((string) ($art['articleNo'] ?? '')) !== '')
                                                    <p class="font-medium text-stone-800">№ {{ $art['articleNo'] }}</p>
                                                @endif
                                                @if(isset($art['articleId']) && $art['articleId'] !== null)
                                                    <p class="text-[11px] text-stone-500">ID в каталоге: {{ $art['articleId'] }}</p>
                                                @endif
                                                @if($details !== [])
                                                    <dl class="mt-2 space-y-1 border-t border-stone-100 pt-2 text-[11px] text-stone-600">
                                                        @foreach($details as $d)
                                                            @if(is_array($d) && isset($d['label'], $d['value']))
                                                                <div class="flex gap-2">
                                                                    <dt class="shrink-0 max-w-[45%] font-medium text-stone-700">{{ $d['label'] }}</dt>
                                                                    <dd class="min-w-0 break-words text-stone-600">{{ $d['value'] }}</dd>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                    </dl>
                                                @endif
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-xs text-stone-600">Выберите конечный раздел в дереве категорий.</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </fieldset>

    <div class="mx-auto max-w-5xl rounded-2xl border border-orange-100/90 bg-gradient-to-b from-white/95 to-orange-50/30 p-4 shadow-lg shadow-orange-950/10 backdrop-blur-sm sm:p-5 md:p-6">
        <h2 class="mb-0.5 text-center text-lg font-bold text-stone-900 sm:text-xl">Подбор запчасти по автомобилю</h2>
        <p class="mb-4 text-center text-xs text-stone-600 sm:mb-5 sm:text-sm">Марка → модификация → категория → деталь</p>

        @php
            $hfSelect = 'hf-select w-full min-w-0 rounded-lg border-orange-200/90 bg-white px-2.5 py-2 text-sm text-stone-900 shadow-sm ring-1 ring-orange-100/80 focus:border-orange-500 focus:outline-none focus:ring-1 focus:ring-orange-500/40 disabled:cursor-not-allowed disabled:opacity-50';
        @endphp

        <div class="mx-auto grid grid-cols-1 gap-x-3 gap-y-3 sm:grid-cols-2 lg:grid-cols-12 lg:gap-x-2 lg:gap-y-2">
            <div class="min-w-0 sm:col-span-1 lg:col-span-3">
                <label for="hf-make" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Марка</label>
                <select id="hf-make" wire:model.live="vehicleMake" class="{{ $hfSelect }}">
                    <option value="">Марка</option>
                    @foreach($this->vehicleMakes as $make)
                        <option value="{{ $make }}">{{ $make }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-1 lg:col-span-9">
                <label for="hf-variant" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Модель (двигатель, кузов, годы)</label>
                <select id="hf-variant" wire:model.live="vehicleId" class="{{ $hfSelect }}"
                        @disabled($vehicleMake === '')>
                    <option value="0">Модификация</option>
                    @foreach($this->vehicleVariants as $variant)
                        <option value="{{ $variant->id }}">{{ $variant->homePartFinderOptionLabel() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-2 lg:col-span-6">
                <label for="hf-category" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Категория</label>
                <select id="hf-category" wire:key="hf-category-{{ $vehicleId }}" wire:model.live="categoryId" class="{{ $hfSelect }}"
                        @disabled($vehicleId <= 0)>
                    <option value="0">Категория</option>
                    @foreach($this->categoriesForVehicle as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-2 lg:col-span-6">
                <label for="hf-part" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Деталь</label>
                <select id="hf-part" wire:key="hf-part-{{ $vehicleId }}-{{ $categoryId }}" wire:model.live="productId" class="{{ $hfSelect }}"
                        @disabled($categoryId <= 0)>
                    <option value="0">Деталь</option>
                    @foreach($this->partsForCategory as $part)
                        <option value="{{ $part->id }}">{{ $part->name }} — {{ $part->sku }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($vehicleMake !== '' || $vehicleId > 0 || $categoryId > 0 || $productId > 0)
            <div class="mt-4 flex justify-center sm:mt-3">
                <button type="button" wire:click="clearSelection"
                        class="text-sm font-medium text-orange-800 underline decoration-orange-300 underline-offset-2 transition hover:text-orange-950">
                    Сбросить подбор
                </button>
            </div>
        @endif
    </div>

    @if($this->selectedProduct)
        @php $main = $this->selectedProduct; @endphp
        <section class="mt-10 sm:mt-12" aria-labelledby="hf-result-title">
            <h2 id="hf-result-title" class="mb-2 text-xl font-bold text-stone-900 sm:text-2xl">Выбранная деталь и аналоги</h2>
            @if($this->selectedVehicleLabel)
                <p class="mb-1 text-sm text-orange-800">Подбор: {{ $this->selectedVehicleLabel }}</p>
            @endif
            <p class="mb-6 text-sm text-slate-600">
                Первая карточка — выбранный товар; далее только аналоги, которые есть в каталоге как отдельные позиции.
            </p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:items-stretch">
                <div class="flex h-full min-h-0 flex-col gap-3">
                    <p class="shrink-0 text-center text-[11px] font-bold uppercase tracking-wide text-orange-900 sm:text-left">Выбранная деталь</p>
                    <x-storefront-product-card
                        :product="$main"
                        :selectedVehicleLabel="$this->selectedVehicleLabel"
                        :hide-cross-preview="true"
                        class="min-h-0 flex-1 ring-2 ring-orange-400/50"
                    >
                        <x-slot name="cart">
                            @livewire('storefront.add-to-cart-button', ['product' => $main], key('hf-cart-'.$main->id))
                        </x-slot>
                        <p class="text-center sm:text-left">
                            <a href="{{ route('product.show', $main) }}{{ count($this->productUrlVehicleQuery) ? '?'.http_build_query($this->productUrlVehicleQuery) : '' }}" class="text-xs font-semibold text-orange-800 underline decoration-orange-200 underline-offset-2 hover:decoration-orange-500">
                                Полная карточка товара →
                            </a>
                        </p>
                    </x-storefront-product-card>
                </div>

                @foreach($this->crossAnalogRows as $row)
                    <div class="flex h-full min-h-0 flex-col gap-2">
                        <p class="shrink-0 text-center text-[11px] font-bold uppercase tracking-wide text-slate-600 sm:text-left">Аналог</p>
                        <x-storefront-product-card
                            split-layout
                            class="min-h-0 flex-1"
                            :product="$row->linked"
                            :selectedVehicleLabel="$this->selectedVehicleLabel"
                            :cross-caption="'По кроссу: '.$row->cross->storefrontAnalogLabel()"
                        >
                            <x-slot name="cart">
                                @livewire('storefront.add-to-cart-button', ['product' => $row->linked], key('hf-cart-analog-'.$row->linked->id.'-'.$loop->index))
                            </x-slot>
                            <p class="text-center sm:text-left">
                                <a href="{{ route('product.show', $row->linked) }}{{ count($this->productUrlVehicleQuery) ? '?'.http_build_query($this->productUrlVehicleQuery) : '' }}" class="text-xs font-semibold text-orange-800 underline decoration-orange-200 underline-offset-2 hover:decoration-orange-500">
                                    Полная карточка товара →
                                </a>
                            </p>
                        </x-storefront-product-card>
                    </div>
                @endforeach
            </div>

        </section>
    @elseif($categoryId > 0 && $this->partsForCategory->isEmpty())
        <p class="mt-10 text-center text-slate-600">В этой категории для выбранного авто пока нет позиций.</p>
    @endif

    @if(trim($search) !== '' && mb_strlen(trim($search)) >= 2)
        <section class="mt-14 border-t border-orange-100/90 pt-10" aria-labelledby="hf-search-heading">
            <h2 id="hf-search-heading" class="mb-2 text-xl font-bold text-stone-900">Результаты поиска</h2>
            <p class="mb-6 text-sm text-slate-600">Запрос: «{{ trim($search) }}»</p>
            @if($this->globalSearchResults->isEmpty())
                <p class="text-center text-slate-600">Ничего не найдено.</p>
            @else
                <div class="grid grid-cols-1 items-start gap-6 sm:grid-cols-2">
                    @foreach($this->globalSearchResults as $product)
                        <x-storefront-product-card :product="$product" :selectedVehicleLabel="null" compact-preview />
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
