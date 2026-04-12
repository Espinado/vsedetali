@extends('layouts.storefront')

@section('title', $product->meta_title ?: $product->name)

@push('head')
    @isset($productJsonLd)
        <script type="application/ld+json">
            {!! json_encode($productJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endisset
@endpush

@section('content')
    <nav class="mb-6 -mx-1 overflow-x-auto whitespace-nowrap px-1 text-sm text-slate-500 sm:whitespace-normal sm:overflow-visible" aria-label="Навигация">
        <a href="{{ route('home') }}" class="font-medium text-slate-700 hover:text-slate-900">Главная</a>
        @if($product->category)
            @foreach($product->category->ancestorsChainForStorefront() as $cat)
                <span class="mx-1">/</span>
                <span class="text-slate-700">{{ $cat->name }}</span>
            @endforeach
        @endif
    </nav>

    <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:gap-10 xl:gap-12">
        @php
            /** Главное фото: is_main, иначе первое по sort; миниатюры — в том же порядке (главное первым). */
            $galleryImages = $product->images->sortBy(fn (\App\Models\ProductImage $i) => [$i->is_main ? 0 : 1, $i->sort]);
        @endphp
        {{-- Галерея + заказ под фото (компактнее половины экрана) --}}
        <div class="w-full shrink-0 lg:max-w-[min(100%,22rem)] xl:max-w-sm">
            <div class="mx-auto w-full max-w-sm lg:mx-0">
                <div class="mb-3 aspect-square overflow-hidden rounded-2xl border border-orange-100/90 bg-stone-100 shadow-sm">
                    @if($product->mainImage?->storage_url)
                        <img src="{{ $product->mainImage->storage_url }}"
                             alt="{{ $product->mainImage->alt ?? $product->name }}"
                             class="h-full w-full object-contain"
                             id="product-main-image">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-slate-400">Нет фото</div>
                    @endif
                </div>
                @if($galleryImages->count() > 1)
                    <div class="flex gap-2 overflow-x-auto pb-2 [-webkit-overflow-scrolling:touch]">
                        @foreach($galleryImages as $image)
                            <button type="button"
                                    class="h-14 w-14 min-h-[3.25rem] min-w-[3.25rem] shrink-0 overflow-hidden rounded-lg border-2 border-orange-100 transition hover:border-orange-300 focus:border-orange-500 focus:outline-none"
                                    onclick="document.getElementById('product-main-image').src = '{{ $image->storage_url }}'">
                                <img src="{{ $image->storage_url }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="mt-4 space-y-3 rounded-xl border border-orange-100/90 bg-gradient-to-br from-orange-50/90 to-amber-50/50 p-4 shadow-sm ring-1 ring-orange-100/60">
                    <p class="text-2xl font-bold tracking-tight text-orange-800 sm:text-3xl">
                        {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
                    </p>
                    <p>
                        @if($product->in_stock)
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200/80">В наличии</span>
                            @if($product->total_stock < 10)
                                <span class="ml-2 text-sm text-stone-500">осталось {{ $product->total_stock }} шт.</span>
                            @endif
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-900 ring-1 ring-amber-200/80">Под заказ</span>
                        @endif
                    </p>
                    @livewire('storefront.add-to-cart-button', ['product' => $product])
                </div>
            </div>
        </div>

        {{-- Описание товара --}}
        <div class="min-w-0 flex-1">
            @if($product->brand)
                <p class="text-sm text-slate-500 mb-1">{{ $product->brand->name }}</p>
            @endif
            <h1 class="mb-2 text-xl font-bold text-slate-900 sm:text-2xl">{{ $product->name }}</h1>
            <p class="text-slate-600 mb-6">Артикул: <span class="font-mono">{{ $product->sku }}</span></p>

            {{-- Показ «Кратко»: при возврате OEM в блок добавить || $product->oemNumbers->isNotEmpty() в условие ниже --}}
            @if($vehiclesCompatLinks->isNotEmpty())
                <div class="mb-6 rounded-2xl border border-orange-100/90 bg-gradient-to-br from-orange-50/80 to-amber-50/40 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3">Кратко</h2>

                    <div class="space-y-2 text-sm">
                        {{-- OEM в «Кратко» (временно скрыто)
                        @if($product->oemNumbers->isNotEmpty())
                            <div>
                                <p class="text-slate-500 mb-1">OEM номера</p>
                                <p class="font-mono text-slate-800">{{ $product->oemNumbers->take(5)->pluck('oem_number')->join(', ') }}</p>
                            </div>
                        @endif
                        --}}

                        @if($vehiclesCompatLinks->isNotEmpty())
                            <div>
                                <p class="text-slate-500 mb-1.5">Совместимость</p>
                                <p class="text-slate-800 leading-relaxed text-[15px]">
                                    @foreach($vehiclesCompatLinks as $row)
                                        @php
                                            $v = $row['vehicle'];
                                            $midYear = 0;
                                            if ($v->year_from !== null && $v->year_to !== null) {
                                                $midYear = (int) floor(((int) $v->year_from + (int) $v->year_to) / 2);
                                            } elseif ($v->year_from !== null) {
                                                $midYear = (int) $v->year_from;
                                            } elseif ($v->year_to !== null) {
                                                $midYear = (int) $v->year_to;
                                            }
                                            $byCarQuery = array_filter([
                                                'vehicleId' => $v->id,
                                                'vehicleMake' => trim((string) $v->make),
                                                'vehicleModel' => trim((string) $v->model),
                                                'vehicleYear' => $midYear > 0 ? $midYear : null,
                                            ], fn ($x) => $x !== null && $x !== '');
                                        @endphp
                                        @unless($loop->first)<span class="text-slate-400">, </span>@endunless
                                        <a href="{{ route('vehicle.by_car', $byCarQuery) }}"
                                           class="text-slate-900 underline decoration-slate-300 hover:decoration-slate-700 underline-offset-2">
                                            {{ $row['label'] }}
                                        </a>
                                    @endforeach
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($product->short_description)
                <p class="text-slate-600 mb-6">{{ $product->short_description }}</p>
            @endif

            @if($product->weight)
                <p class="mt-6 text-sm text-slate-500">Вес: {{ number_format($product->weight, 2) }} кг</p>
            @endif
        </div>
    </div>

    {{-- Описание --}}
    @if($product->description)
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Описание</h2>
            <div class="prose prose-slate max-w-none overflow-x-auto break-words text-slate-600">
                {!! nl2br(e($product->description)) !!}
            </div>
        </section>
    @endif

    {{-- OEM номера (полный список) — временно скрыто
    @if($product->oemNumbers->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">OEM номера</h2>
            <p class="text-slate-600 font-mono text-sm">{{ $product->oemNumbers->pluck('oem_number')->join(', ') }}</p>
        </section>
    @endif
    --}}

    {{-- Аналоги: карточки одного размера с превью --}}
    @if($crossAnalogItems->isNotEmpty())
        <section id="analogs" class="mt-12 pt-8 border-t border-slate-200 scroll-mt-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-2">Аналоги других производителей</h2>
            <p class="text-sm text-slate-500 mb-6">Показываются только аналоги, которые есть в нашем каталоге как отдельные товары (совпадение номера).</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($crossAnalogItems as $item)
                    <article class="flex h-full min-h-[22rem] flex-col overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-100/80 transition hover:border-orange-200/80 hover:shadow-md">
                        <a href="{{ route('product.show', $item->linked) }}" class="block shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                            <div class="flex aspect-[5/4] items-center justify-center bg-stone-50">
                                @if($item->linked->mainImage?->storage_url)
                                    <img src="{{ $item->linked->mainImage->storage_url }}"
                                         alt="{{ $item->linked->mainImage->alt ?? $item->linked->name }}"
                                         class="max-h-full max-w-full object-contain p-3"
                                         loading="lazy"
                                         width="200"
                                         height="160">
                                @else
                                    <span class="px-4 text-center text-xs text-slate-400">Нет фото</span>
                                @endif
                            </div>
                        </a>
                        <div class="flex min-h-0 flex-1 flex-col gap-2 border-t border-slate-100 p-4">
                            <div class="text-xs text-slate-500">
                                <span class="font-medium text-slate-700">{{ $item->cross->manufacturer_name ?: 'Аналог' }}</span>
                                <span class="mx-1 text-slate-300">·</span>
                                <span class="font-mono text-slate-800">{{ $item->cross->cross_number }}</span>
                            </div>
                            <a href="{{ route('product.show', $item->linked) }}" class="line-clamp-2 min-h-[2.5rem] text-sm font-semibold leading-snug text-slate-900 hover:text-orange-800">
                                {{ $item->linked->name }}
                            </a>
                            @if($item->linked->brand)
                                <p class="text-xs text-slate-500">{{ $item->linked->brand->name }}</p>
                            @endif
                            <p class="font-mono text-[11px] text-slate-400">{{ $item->linked->sku }}</p>
                            <p class="mt-auto pt-2 text-lg font-bold text-orange-800">
                                {{ number_format($item->linked->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
                            </p>
                            @if($item->linked->in_stock)
                                <p class="text-xs font-medium text-emerald-700">В наличии</p>
                            @else
                                <p class="text-xs font-medium text-amber-800">Под заказ</p>
                            @endif
                            <div class="pt-2">
                                @livewire('storefront.add-to-cart-button', ['product' => $item->linked, 'compact' => true], key('product-show-analog-'.$item->linked->id))
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
