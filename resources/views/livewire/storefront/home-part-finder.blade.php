<div class="relative w-full">
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
                <p class="mt-4 text-center text-sm font-medium text-stone-700">Загружаем подбор…</p>
            </div>
        </div>
    </div>

    <fieldset class="mx-auto mb-5 max-w-5xl border-0 p-0">
        <legend class="mb-1.5 block w-full text-center text-xs font-medium text-stone-600 sm:text-left sm:text-[13px]">Поиск по номеру</legend>
        @livewire('storefront.header-search', ['variant' => 'hero'], key('storefront-home-search'))
    </fieldset>

    <div class="mx-auto max-w-5xl rounded-2xl border border-orange-100/90 bg-gradient-to-b from-white/95 to-orange-50/30 p-4 shadow-lg shadow-orange-950/10 backdrop-blur-sm sm:p-5 md:p-6">
        <h2 class="mb-0.5 text-center text-lg font-bold text-stone-900 sm:text-xl">Подбор запчасти по автомобилю</h2>
        <p class="mb-4 text-center text-xs text-stone-600 sm:mb-5 sm:text-sm">Марка → модель → год → категория → деталь</p>

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

            <div class="min-w-0 sm:col-span-1 lg:col-span-6">
                <label for="hf-model" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Модель</label>
                <select id="hf-model" wire:model.live="vehicleModel" class="{{ $hfSelect }}"
                        @disabled($vehicleMake === '')>
                    <option value="">Модель</option>
                    @foreach($this->vehicleModels as $model)
                        <option value="{{ $model }}">{{ $model }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-2 lg:col-span-3">
                <label for="hf-year" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Год</label>
                <select id="hf-year" wire:model.live="vehicleYear" class="{{ $hfSelect }}"
                        @disabled($vehicleModel === '')>
                    <option value="0">Год</option>
                    @foreach($this->vehicleYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-2 lg:col-span-6">
                <label for="hf-category" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Категория</label>
                <select id="hf-category" wire:model.live="categoryId" class="{{ $hfSelect }}"
                        @disabled($vehicleYear <= 0)>
                    <option value="0">Категория</option>
                    @foreach($this->categoriesForVehicle as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-0 sm:col-span-2 lg:col-span-6">
                <label for="hf-part" class="mb-1 block text-xs font-medium text-stone-600 sm:text-[13px]">Деталь</label>
                <select id="hf-part" wire:model.live="productId" class="{{ $hfSelect }}"
                        @disabled($categoryId <= 0)>
                    <option value="0">Деталь</option>
                    @foreach($this->partsForCategory as $part)
                        <option value="{{ $part->id }}">{{ $part->name }} — {{ $part->sku }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($vehicleMake !== '' || $vehicleModel !== '' || $vehicleYear > 0 || $categoryId > 0 || $productId > 0)
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
                            <a href="{{ route('product.show', $main) }}" class="text-xs font-semibold text-orange-800 underline decoration-orange-200 underline-offset-2 hover:decoration-orange-500">
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
                                <a href="{{ route('product.show', $row->linked) }}" class="text-xs font-semibold text-orange-800 underline decoration-orange-200 underline-offset-2 hover:decoration-orange-500">
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
