@props([
    'product',
    'selectedVehicleLabel' => null,
    'hideCrossPreview' => false,
    'crossCaption' => null,
    /** Маленькое превью слева (результаты поиска на главной) */
    'compactPreview' => false,
    /**
     * Та же сетка «фото слева + текст», что у карточки с корзиной, но без футера.
     * Нужна в паре с выбранным товаром (аналоги в одной строке сетки).
     */
    'splitLayout' => false,
])

@php
    $compatLines = (! $selectedVehicleLabel && $product->vehicles->isNotEmpty())
        ? $product->compatibilityLabelsForStorefrontCard(2)
        : [];
    $showCompat = $compatLines !== [];
    $showCross = ! $hideCrossPreview && $product->crossNumbers->isNotEmpty();
    $showMetaStrip = ! $compactPreview && ($showCross || $showCompat);
@endphp

@if($slot->isEmpty() && $compactPreview)
    <a href="{{ route('product.show', $product) }}"
       {{ $attributes->merge(['class' => 'card-store-product group flex min-h-0 min-w-0 items-start gap-3 p-3 sm:gap-4 sm:p-4']) }}>
        <div class="storefront-card-media h-16 w-16 shrink-0 rounded-lg sm:h-20 sm:w-20">
            @if($product->mainImage?->storage_url)
                <img src="{{ $product->mainImage->storage_url }}"
                     alt="{{ $product->mainImage->alt ?? $product->name }}"
                     class="transition duration-300 group-hover:scale-[1.03]">
            @else
                <span class="absolute inset-0 flex items-center justify-center text-[10px] text-slate-400">Нет фото</span>
            @endif
        </div>
        <div class="min-w-0 flex-1 pt-0.5">
            @if($crossCaption)
                <p class="mb-1 rounded-md bg-orange-50/90 px-1.5 py-1 text-[10px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $crossCaption }}</p>
            @endif
            @if($product->brand)
                <p class="mb-0.5 text-xs text-slate-500">{{ $product->brand->name }}</p>
            @endif
            <p class="mb-0.5 font-mono text-[11px] text-slate-500">{{ $product->sku }}</p>
            <h3 class="line-clamp-2 text-sm font-medium leading-snug text-stone-900 transition group-hover:text-orange-800">{{ $product->name }}</h3>
            <p class="mt-1.5 text-base font-bold text-orange-800 sm:text-lg">
                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
            </p>
            @if($product->in_stock)
                <p class="mt-0.5 text-[11px] font-semibold text-emerald-700">
                    @if($product->total_stock < 10)
                        В наличии ({{ $product->total_stock }})
                    @else
                        В наличии
                    @endif
                </p>
            @else
                <p class="mt-0.5 text-[11px] font-semibold text-amber-700">Под заказ</p>
            @endif
            @if($selectedVehicleLabel)
                <p class="mt-1 line-clamp-2 text-[11px] font-medium text-orange-700">
                    Подходит для {{ $selectedVehicleLabel }}
                </p>
            @endif
        </div>
    </a>
@elseif($splitLayout)
    @php
        $compatLinesSplit = (! $selectedVehicleLabel && $product->vehicles->isNotEmpty())
            ? $product->compatibilityLabelsForStorefrontCard(3)
            : [];
        $showCompatSplit = $compatLinesSplit !== [];
        $showCrossSplit = ! $hideCrossPreview && $product->crossNumbers->isNotEmpty();
        $showMetaSplit = $showCrossSplit || $showCompatSplit;
    @endphp
    @if($slot->isEmpty())
        <a href="{{ route('product.show', $product) }}"
           {{ $attributes->merge(['class' => 'card-store-product group flex h-full min-h-0 flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2 md:grid md:grid-cols-[minmax(11rem,15.5rem)_1fr] md:items-start md:gap-0 md:focus-visible:ring-offset-0 lg:grid-cols-[minmax(12rem,17rem)_1fr]']) }}>
            <div class="storefront-card-media h-40 w-full shrink-0 sm:h-44 md:h-48 lg:h-52">
                @if($product->mainImage?->storage_url)
                    <img src="{{ $product->mainImage->storage_url }}"
                         alt="{{ $product->mainImage->alt ?? $product->name }}"
                         class="transition duration-300 group-hover:scale-[1.02]">
                @else
                    <span class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Нет фото</span>
                @endif
            </div>
            <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-3 px-4 py-3 sm:px-5 sm:py-4 md:h-full">
                <div class="min-w-0 flex-1">
                    @if($crossCaption)
                        <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $crossCaption }}</p>
                    @endif
                    @if($product->brand)
                        <p class="mb-0.5 text-xs text-slate-500">{{ $product->brand->name }}</p>
                    @endif
                    <p class="mb-0.5 font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                    <h3 class="line-clamp-2 font-medium text-stone-900 transition group-hover:text-orange-800 sm:line-clamp-3">{{ $product->name }}</h3>
                    <p class="mt-2 text-lg font-bold text-orange-800 sm:text-xl">
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
                    @if($selectedVehicleLabel)
                        <p class="mt-1.5 text-xs font-medium text-orange-700">
                            Подходит для {{ $selectedVehicleLabel }}
                        </p>
                    @endif
                </div>
                @if($showMetaSplit)
                    <div class="shrink-0 space-y-1 rounded-lg border border-orange-100/80 bg-orange-50/40 p-2.5 sm:p-3">
                        @if($showCrossSplit)
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Аналоги:</span>
                                <span class="font-mono">{{ $product->crossNumbers->take(3)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}</span>
                            </p>
                        @endif
                        @if($showCompatSplit)
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Совместимость:</span>
                                {{ implode(', ', $compatLinesSplit) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </a>
    @else
        @php
            $hasCartSlotSplit = isset($cart) && ! $cart->isEmpty();
        @endphp
        <div {{ $attributes->merge(['class' => 'card-store-product flex h-full min-h-0 flex-col overflow-hidden md:grid md:grid-cols-[minmax(11rem,15.5rem)_1fr] md:items-start md:gap-0 lg:grid-cols-[minmax(12rem,17rem)_1fr]']) }}>
            <div class="flex min-h-0 min-w-0 flex-col md:h-full">
                <a href="{{ route('product.show', $product) }}"
                   class="group storefront-card-media h-40 w-full shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-500 sm:h-44 md:h-48 lg:h-52">
                    <span class="sr-only">{{ $product->name }}</span>
                    @if($product->mainImage?->storage_url)
                        <img src="{{ $product->mainImage->storage_url }}"
                             alt="{{ $product->mainImage->alt ?? $product->name }}"
                             class="transition duration-300 group-hover:scale-[1.02]">
                    @else
                        <span class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Нет фото</span>
                    @endif
                </a>
                @if($hasCartSlotSplit)
                    <div class="shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-orange-50/50 to-white p-2.5 sm:p-3">
                        {{ $cart }}
                    </div>
                @endif
            </div>
            <div class="flex min-h-0 min-w-0 flex-1 flex-col md:h-full">
                <a href="{{ route('product.show', $product) }}"
                   class="group block min-h-0 flex-1 px-4 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-500 sm:px-5 sm:py-4">
                    @if($crossCaption)
                        <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $crossCaption }}</p>
                    @endif
                    @if($product->brand)
                        <p class="mb-0.5 text-xs text-slate-500">{{ $product->brand->name }}</p>
                    @endif
                    <p class="mb-0.5 font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                    <h3 class="line-clamp-2 font-medium text-stone-900 transition group-hover:text-orange-800 sm:line-clamp-3">{{ $product->name }}</h3>
                    <p class="mt-2 text-lg font-bold text-orange-800 sm:text-xl">
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
                    @if($selectedVehicleLabel)
                        <p class="mt-1.5 text-xs font-medium text-orange-700">
                            Подходит для {{ $selectedVehicleLabel }}
                        </p>
                    @endif
                    @if($showMetaSplit)
                        <div class="mt-3 space-y-1 rounded-lg border border-orange-100/80 bg-orange-50/40 p-2.5 sm:p-3">
                            @if($showCrossSplit)
                                <p class="text-xs text-slate-600">
                                    <span class="font-medium text-slate-700">Аналоги:</span>
                                    <span class="font-mono">{{ $product->crossNumbers->take(3)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}</span>
                                </p>
                            @endif
                            @if($showCompatSplit)
                                <p class="text-xs text-slate-600">
                                    <span class="font-medium text-slate-700">Совместимость:</span>
                                    {{ implode(', ', $compatLinesSplit) }}
                                </p>
                            @endif
                        </div>
                    @endif
                </a>
                @if($slot->isNotEmpty())
                    <div class="shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-orange-50/40 to-white px-4 py-3 sm:px-5 sm:py-4">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    @endif
@elseif($slot->isEmpty())
    <a href="{{ route('product.show', $product) }}"
       {{ $attributes->merge(['class' => 'card-store-product group flex min-h-0 min-w-0 flex-col']) }}>
        <div class="storefront-card-media aspect-square w-full">
            @if($product->mainImage?->storage_url)
                <img src="{{ $product->mainImage->storage_url }}"
                     alt="{{ $product->mainImage->alt ?? $product->name }}"
                     class="transition duration-300 group-hover:scale-[1.03]">
            @else
                <span class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Нет фото</span>
            @endif
        </div>
        <div class="p-4">
            @if($crossCaption)
                <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $crossCaption }}</p>
            @endif
            @if($product->brand)
                <p class="mb-0.5 text-xs text-slate-500">{{ $product->brand->name }}</p>
            @endif
            <p class="mb-0.5 font-mono text-xs text-slate-500">{{ $product->sku }}</p>
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
            @if($selectedVehicleLabel)
                <p class="mt-1 text-xs font-medium text-orange-700">
                    Подходит для {{ $selectedVehicleLabel }}
                </p>
            @endif

            @if($showMetaStrip)
                <div class="mt-3 space-y-1.5 rounded-xl border border-orange-100/80 bg-orange-50/40 p-3">
                    @if($showCross)
                        <p class="text-xs text-slate-600">
                            <span class="font-medium text-slate-700">Аналоги:</span>
                            <span class="font-mono">{{ $product->crossNumbers->take(2)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}</span>
                        </p>
                    @endif
                    @if($showCompat)
                        <p class="text-xs text-slate-600">
                            <span class="font-medium text-slate-700">Совместимость:</span>
                            {{ implode(', ', $compatLines) }}
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </a>
@else
    @php
        $compatLinesFeatured = (! $selectedVehicleLabel && $product->vehicles->isNotEmpty())
            ? $product->compatibilityLabelsForStorefrontCard(3)
            : [];
        $showCompatFeatured = $compatLinesFeatured !== [];
        $showCrossFeatured = ! $hideCrossPreview && $product->crossNumbers->isNotEmpty();
        $showMetaFeatured = $showCrossFeatured || $showCompatFeatured;
        $hasCartSlot = isset($cart) && ! $cart->isEmpty();
    @endphp
    <div {{ $attributes->merge(['class' => 'card-store-product flex h-full min-h-0 flex-col overflow-hidden md:grid md:grid-cols-[minmax(11rem,15.5rem)_1fr] md:items-start md:gap-0 lg:grid-cols-[minmax(12rem,17rem)_1fr]']) }}>
        <div class="flex min-h-0 min-w-0 flex-col md:h-full">
            <a href="{{ route('product.show', $product) }}"
               class="group storefront-card-media h-40 w-full shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-500 sm:h-44 md:h-48 lg:h-52">
                <span class="sr-only">{{ $product->name }}</span>
                @if($product->mainImage?->storage_url)
                    <img src="{{ $product->mainImage->storage_url }}"
                         alt="{{ $product->mainImage->alt ?? $product->name }}"
                         class="transition duration-300 group-hover:scale-[1.02]">
                @else
                    <span class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Нет фото</span>
                @endif
            </a>
            @if($hasCartSlot)
                <div class="shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-orange-50/50 to-white p-2.5 sm:p-3">
                    {{ $cart }}
                </div>
            @endif
        </div>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col md:h-full">
            <a href="{{ route('product.show', $product) }}"
               class="group block min-h-0 flex-1 px-4 py-3 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-500 sm:px-5 sm:py-4">
                @if($crossCaption)
                    <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $crossCaption }}</p>
                @endif
                @if($product->brand)
                    <p class="mb-0.5 text-xs text-slate-500">{{ $product->brand->name }}</p>
                @endif
                <p class="mb-0.5 font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                <h3 class="line-clamp-2 font-medium text-stone-900 transition group-hover:text-orange-800 sm:line-clamp-3">{{ $product->name }}</h3>
                <p class="mt-2 text-lg font-bold text-orange-800 sm:text-xl">
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
                @if($selectedVehicleLabel)
                    <p class="mt-1.5 text-xs font-medium text-orange-700">
                        Подходит для {{ $selectedVehicleLabel }}
                    </p>
                @endif

                @if($showMetaFeatured)
                    <div class="mt-3 space-y-1 rounded-lg border border-orange-100/80 bg-orange-50/40 p-2.5 sm:p-3">
                        @if($showCrossFeatured)
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Аналоги:</span>
                                <span class="font-mono">{{ $product->crossNumbers->take(3)->map(fn ($c) => $c->storefrontAnalogLabel())->join(', ') }}</span>
                            </p>
                        @endif
                        @if($showCompatFeatured)
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Совместимость:</span>
                                {{ implode(', ', $compatLinesFeatured) }}
                            </p>
                        @endif
                    </div>
                @endif
            </a>
            @if($slot->isNotEmpty())
                <div class="shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-orange-50/40 to-white px-4 py-3 sm:px-5 sm:py-4">
                    {{ $slot }}
                </div>
            @endif
        </div>
    </div>
@endif
