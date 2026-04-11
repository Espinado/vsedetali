<div class="relative w-full max-w-full md:max-w-xl">
    <form wire:submit.prevent="search" class="relative">
        <input
            type="search"
            wire:model.live.debounce.300ms="query"
            placeholder="Поиск по названию, SKU, OEM или аналогу"
            class="w-full min-w-0 rounded-lg border border-stone-600 bg-stone-800/90 py-2.5 pr-20 pl-3 text-sm text-stone-100 shadow-inner shadow-black/20 placeholder:text-stone-400 focus:border-orange-500 focus:bg-stone-800 focus:ring-2 focus:ring-orange-500/40 sm:px-4"
        >

        @if ($query !== '')
            <button
                type="button"
                wire:click="clearSearch"
                class="absolute right-12 top-1/2 -translate-y-1/2 text-stone-400 transition hover:text-orange-200"
                aria-label="Очистить поиск"
            >
                ×
            </button>
        @endif

        <button
            type="submit"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-orange-400 transition hover:text-orange-300"
        >
            Найти
        </button>
    </form>

    @if ($resultsPanelOpen && mb_strlen(trim($query)) >= 2)
        <div class="absolute left-0 right-0 top-full z-50 mt-2 max-h-[min(70vh,28rem)] overflow-y-auto overscroll-contain rounded-xl border border-orange-100/90 bg-white shadow-xl shadow-orange-950/10">
            @if ($this->results->isNotEmpty())
                <div class="divide-y divide-slate-100">
                    @foreach ($this->results as $product)
                        <a
                            href="{{ route('product.show', $product) }}"
                            wire:key="header-search-{{ $product->id }}"
                            wire:click="closeResultsPanel"
                            class="flex items-center gap-3 px-4 py-3 transition hover:bg-orange-50/80"
                        >
                            <div class="h-14 w-14 shrink-0 overflow-hidden rounded bg-slate-100">
                                @if ($product->mainImage?->storage_url)
                                    <img
                                        src="{{ $product->mainImage->storage_url }}"
                                        alt="{{ $product->mainImage->alt ?? $product->name }}"
                                        class="h-full w-full object-cover"
                                    >
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">Нет фото</div>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $product->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $product->brand?->name ? $product->brand->name . ' · ' : '' }}{{ $product->sku }}
                                </p>
                                @if ($product->oemNumbers->isNotEmpty() || $product->crossNumbers->isNotEmpty())
                                    <p class="mt-1 truncate text-xs text-slate-500">
                                        @if ($product->oemNumbers->isNotEmpty())
                                            OEM: {{ $product->oemNumbers->take(2)->pluck('oem_number')->join(', ') }}
                                        @endif
                                        @if ($product->oemNumbers->isNotEmpty() && $product->crossNumbers->isNotEmpty())
                                            ·
                                        @endif
                                        @if ($product->crossNumbers->isNotEmpty())
                                            Аналоги: {{ $product->crossNumbers->take(2)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}
                                        @endif
                                    </p>
                                @endif
                                <p class="mt-1 text-sm font-semibold text-slate-900">
                                    {{ number_format((float) $product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="border-t border-orange-100 bg-orange-50/50 px-4 py-3">
                    <a
                        href="{{ route('catalog', ['search' => trim($query)]) }}"
                        wire:click="closeResultsPanel"
                        class="text-sm font-semibold text-orange-700 transition hover:text-orange-800"
                    >
                        Смотреть все результаты
                    </a>
                </div>
            @else
                <div class="px-4 py-4 text-sm text-slate-500">
                    Ничего не найдено. Попробуйте другое название, артикул, OEM или номер аналога.
                </div>
            @endif
        </div>
    @endif
</div>
