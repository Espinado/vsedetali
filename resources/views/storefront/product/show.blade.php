@extends('layouts.storefront')

@section('title', $product->name)

@section('content')
    <nav class="text-sm text-slate-500 mb-6">
        <a href="{{ route('catalog') }}" class="hover:text-slate-700">Каталог</a>
        @if($product->category)
            <span class="mx-1">/</span>
            <a href="{{ route('catalog', ['categorySlug' => $product->category->slug]) }}" class="hover:text-slate-700">{{ $product->category->name }}</a>
        @endif
    </nav>

    <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">
        {{-- Галерея --}}
        <div class="lg:w-1/2 shrink-0">
            <div class="aspect-square rounded-lg border border-slate-200 bg-slate-100 overflow-hidden mb-4">
                @if($product->images->isNotEmpty())
                    <img src="{{ asset('storage/' . $product->images->first()->path) }}"
                         alt="{{ $product->images->first()->alt ?? $product->name }}"
                         class="w-full h-full object-contain"
                         id="product-main-image">
                @else
                    <div class="w-full h-full flex items-center justify-center text-slate-400">Нет фото</div>
                @endif
            </div>
            @if($product->images->count() > 1)
                <div class="flex gap-2 overflow-x-auto pb-2">
                    @foreach($product->images as $image)
                        <button type="button"
                                class="w-16 h-16 shrink-0 rounded border-2 border-slate-200 hover:border-slate-400 overflow-hidden focus:border-slate-600"
                                onclick="document.getElementById('product-main-image').src = '{{ asset('storage/' . $image->path) }}'">
                            <img src="{{ asset('storage/' . $image->path) }}" alt="" class="w-full h-full object-cover">
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
            <h1 class="text-2xl font-bold text-slate-900 mb-2">{{ $product->name }}</h1>
            <p class="text-slate-600 mb-4">Артикул: <span class="font-mono">{{ $product->sku }}</span></p>

            <p class="text-2xl font-semibold text-slate-900 mb-2">
                {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'EUR') }}
            </p>
            <p class="mb-6">
                @if($product->in_stock)
                    <span class="text-green-600 font-medium">В наличии</span>
                    @if($product->total_stock < 10)
                        <span class="text-slate-500 text-sm">(осталось {{ $product->total_stock }} шт.)</span>
                    @endif
                @else
                    <span class="text-amber-600 font-medium">Под заказ</span>
                @endif
            </p>

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
            <div class="prose prose-slate max-w-none text-slate-600">
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

    {{-- Совместимость (автомобили) --}}
    @if($product->vehicles->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Подходит для автомобилей</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach($product->vehicles->take(20) as $vehicle)
                    <li class="px-3 py-1.5 bg-slate-100 rounded text-sm text-slate-700">
                        {{ $vehicle->make }} {{ $vehicle->model }}
                        @if($vehicle->year_from || $vehicle->year_to)
                            ({{ $vehicle->year_from ?? '—' }}–{{ $vehicle->year_to ?? '—' }})
                        @endif
                        @if($vehicle->pivot?->oem_number)
                            <span class="text-slate-500">— {{ $vehicle->pivot->oem_number }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($product->vehicles->count() > 20)
                <p class="mt-2 text-sm text-slate-500">и ещё {{ $product->vehicles->count() - 20 }} моделей</p>
            @endif
        </section>
    @endif

    {{-- OEM номера --}}
    @if($product->oemNumbers->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">OEM номера</h2>
            <p class="text-slate-600 font-mono text-sm">{{ $product->oemNumbers->pluck('oem_number')->join(', ') }}</p>
        </section>
    @endif

    {{-- Кросс-номера (аналоги) --}}
    @if($product->crossNumbers->isNotEmpty())
        <section class="mt-12 pt-8 border-t border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Аналоги (кросс-номера)</h2>
            <p class="text-slate-600 font-mono text-sm">{{ $product->crossNumbers->pluck('cross_number')->join(', ') }}</p>
        </section>
    @endif
@endsection
