@extends('layouts.storefront')

@section('title', 'Главная')

@section('content')
    <div class="mb-10">
        @if($banners->isNotEmpty())
            @php $banner = $banners->first(); @endphp
            @if($bannerImg = $banner->imageUrl())
                <div class="rounded-xl overflow-hidden bg-slate-100 mb-10">
                    @if($href = $banner->resolvedHref())
                        <a href="{{ $href }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 rounded-xl"
                           @if($banner->linkOpensInNewTab()) target="_blank" rel="noopener noreferrer" @endif>
                            <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="w-full h-48 sm:h-64 md:h-80 object-cover">
                        </a>
                    @else
                        <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="w-full h-48 sm:h-64 md:h-80 object-cover">
                    @endif
                </div>
            @endif
        @endif

        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-slate-800 mb-2">{{ $storeName }}</h1>
            <p class="text-slate-600 mb-6">Интернет-магазин автозапчастей</p>
            <a href="{{ route('catalog') }}" class="inline-block px-6 py-3 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium">Перейти в каталог</a>
        </div>

        @if($featuredProducts->isNotEmpty())
            <section>
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Новинки и популярные товары</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($featuredProducts as $product)
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
                                    {{ number_format($product->price, 2) }} {{ \App\Models\Setting::get('currency', 'EUR') }}
                                </p>
                                @if($product->in_stock)
                                    <p class="mt-1 text-xs text-emerald-600">В наличии</p>
                                @else
                                    <p class="mt-1 text-xs text-amber-600">Под заказ</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-6 text-center">
                    <a href="{{ route('catalog') }}" class="text-indigo-600 hover:underline font-medium">Все товары →</a>
                </div>
            </section>
        @endif
    </div>
@endsection
