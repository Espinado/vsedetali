@extends('layouts.storefront')

@section('title', 'Главная')

@section('content')
    <div class="mb-10">
        @if($banners->isNotEmpty())
            @php $banner = $banners->first(); @endphp
            @if($bannerImg = $banner->imageUrl())
                <div class="mb-10 overflow-hidden rounded-2xl border border-orange-100/80 bg-stone-100 shadow-md shadow-orange-950/5">
                    @if($href = $banner->resolvedHref())
                        <a href="{{ $href }}" class="block rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2"
                           @if($banner->linkOpensInNewTab()) target="_blank" rel="noopener noreferrer" @endif>
                            <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="w-full h-48 sm:h-64 md:h-80 object-cover">
                        </a>
                    @else
                        <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="w-full h-48 sm:h-64 md:h-80 object-cover">
                    @endif
                </div>
            @endif
        @endif

        <div class="mb-10 rounded-2xl border border-orange-100/90 bg-white/80 px-4 py-8 text-center shadow-sm shadow-orange-950/5 backdrop-blur-sm sm:px-8">
            <h1 class="mb-2 text-2xl font-bold text-stone-900 sm:text-3xl">{{ $storeName }}</h1>
            <p class="mb-6 text-stone-600">Интернет-магазин автозапчастей — подберём деталь под ваш автомобиль</p>
            <a href="{{ route('catalog') }}" class="btn-store-cta">Перейти в каталог</a>
        </div>

        @if($featuredProducts->isNotEmpty())
            <section>
                <h2 class="mb-4 text-xl font-semibold text-stone-900">Новинки и популярные товары</h2>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($featuredProducts as $product)
                        <a href="{{ route('product.show', $product) }}"
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
                                    <p class="mt-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200/80">В наличии</p>
                                @else
                                    <p class="mt-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200/80">Под заказ</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('catalog') }}" class="font-semibold text-orange-700 transition hover:text-orange-800 hover:underline">Все товары →</a>
                </div>
            </section>
        @endif
    </div>
@endsection
