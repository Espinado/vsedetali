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

    <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">
        {{-- Галерея --}}
        <div class="lg:w-1/2 shrink-0">
            <div class="mb-4 aspect-square overflow-hidden rounded-2xl border border-orange-100/90 bg-stone-100 shadow-sm">
                @if($product->images->isNotEmpty())
                    <img src="{{ $product->images->first()->storage_url }}"
                         alt="{{ $product->images->first()->alt ?? $product->name }}"
                         class="w-full h-full object-contain"
                         id="product-main-image">
                @else
                    <div class="w-full h-full flex items-center justify-center text-slate-400">Нет фото</div>
                @endif
            </div>
            @if($product->images->count() > 1)
                <div class="flex gap-2 overflow-x-auto pb-2 [-webkit-overflow-scrolling:touch]">
                    @foreach($product->images as $image)
                        <button type="button"
                                class="h-16 w-16 min-h-[3.5rem] min-w-[3.5rem] shrink-0 overflow-hidden rounded-lg border-2 border-orange-100 transition hover:border-orange-300 focus:border-orange-500 focus:outline-none"
                                onclick="document.getElementById('product-main-image').src = '{{ $image->storage_url }}'">
                            <img src="{{ $image->storage_url }}" alt="" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Инфо и корзина --}}
        <div class="lg:w-1/2 min-w-0">
            @if($product->brand)
                <p class="text-sm text-slate-500 mb-1">{{ $product->brand->name }}</p>
            @endif
            <h1 class="mb-2 text-xl font-bold text-slate-900 sm:text-2xl">{{ $product->name }}</h1>
            <p class="text-slate-600 mb-4">Артикул: <span class="font-mono">{{ $product->sku }}</span></p>

            <p class="mb-2 text-3xl font-bold tracking-tight text-orange-800 sm:text-4xl">
                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
            </p>
            <p class="mb-6">
                @if($product->in_stock)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200/80">В наличии</span>
                    @if($product->total_stock < 10)
                        <span class="ml-2 text-sm text-stone-500">осталось {{ $product->total_stock }} шт.</span>
                    @endif
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-900 ring-1 ring-amber-200/80">Под заказ</span>
                @endif
            </p>

            @if($product->oemNumbers->isNotEmpty() || $crossAnalogItems->isNotEmpty() || $vehiclesCompatLinks->isNotEmpty())
                <div class="mb-6 rounded-2xl border border-orange-100/90 bg-gradient-to-br from-orange-50/80 to-amber-50/40 p-4 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3">Кратко</h2>

                    <div class="space-y-2 text-sm">
                        @if($product->oemNumbers->isNotEmpty())
                            <div>
                                <p class="text-slate-500 mb-1">OEM номера</p>
                                <p class="font-mono text-slate-800">{{ $product->oemNumbers->take(5)->pluck('oem_number')->join(', ') }}</p>
                            </div>
                        @endif

                        @if($crossAnalogItems->isNotEmpty())
                            <div>
                                <p class="text-slate-500 mb-1">Аналоги в каталоге</p>
                                <p class="text-slate-700">
                                    Найдено в каталоге: {{ $crossAnalogItems->count() }}
                                    <a href="#analogs" class="text-slate-900 underline underline-offset-2">смотреть</a>
                                </p>
                            </div>
                        @endif

                        @if($vehiclesCompatLinks->isNotEmpty())
                            <div>
                                <p class="text-slate-500 mb-1.5">Совместимость</p>
                                <p class="text-slate-800 leading-relaxed text-[15px]">
                                    @foreach($vehiclesCompatLinks as $row)
                                        @unless($loop->first)<span class="text-slate-400">, </span>@endunless
                                        <a href="{{ route('home', ['vehicleId' => $row['vehicle']->id]) }}"
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

            @livewire('storefront.add-to-cart-button', ['product' => $product])

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

    {{-- Характеристики --}}
    @if($product->attributes->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Характеристики</h2>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                @foreach($product->attributes as $attr)
                    <dt class="text-slate-500">{{ $attr->name }}</dt>
                    <dd class="text-slate-800">{{ $attr->value }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    {{-- OEM номера (полный список) --}}
    @if($product->oemNumbers->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">OEM номера</h2>
            <p class="text-slate-600 font-mono text-sm">{{ $product->oemNumbers->pluck('oem_number')->join(', ') }}</p>
        </section>
    @endif

    {{-- Аналоги: номер + ссылка на товар в магазине --}}
    @if($crossAnalogItems->isNotEmpty())
        <section id="analogs" class="mt-12 pt-8 border-t border-slate-200 scroll-mt-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Аналоги других производителей</h2>
            <p class="text-sm text-slate-500 mb-4">Показываются только аналоги, которые есть в нашем каталоге как отдельные товары (совпадение номера).</p>
            <div class="-mx-1 overflow-x-auto rounded-lg border border-slate-200 sm:mx-0 [-webkit-overflow-scrolling:touch]">
                <table class="min-w-[32rem] w-full text-left text-sm sm:min-w-full">
                    <thead class="bg-slate-50 text-slate-600 font-medium border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3">Производитель аналога</th>
                            <th class="px-4 py-3">Номер аналога</th>
                            <th class="px-4 py-3">Товар</th>
                            <th class="px-4 py-3 min-w-[12rem]">Корзина</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($crossAnalogItems as $item)
                            <tr class="hover:bg-slate-50/80 align-top">
                                <td class="px-4 py-3 text-slate-800">{{ $item->cross->manufacturer_name ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-slate-900">{{ $item->cross->cross_number }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('product.show', $item->linked) }}" class="text-slate-900 font-medium hover:underline">
                                        {{ $item->linked->name }}
                                    </a>
                                    @if($item->linked->brand)
                                        <span class="text-slate-500 text-sm"> — {{ $item->linked->brand->name }}</span>
                                    @endif
                                    <span class="block text-xs text-slate-400 font-mono mt-0.5">{{ $item->linked->sku }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @livewire('storefront.add-to-cart-button', ['product' => $item->linked, 'compact' => true], key('product-show-analog-'.$item->linked->id))
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
