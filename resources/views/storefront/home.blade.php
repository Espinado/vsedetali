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
                            <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="h-48 w-full object-cover sm:h-56 md:h-64">
                        </a>
                    @else
                        <img src="{{ $bannerImg }}" alt="{{ $banner->name ?? '' }}" class="h-48 w-full object-cover sm:h-56 md:h-64">
                    @endif
                </div>
            @endif
        @endif

        @livewire('storefront.home-part-finder')
    </div>
@endsection
