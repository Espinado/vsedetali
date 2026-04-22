<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.pwa-head')
    @include('layouts.partials.seo-head')
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col min-w-0 bg-gradient-to-b from-orange-50/90 via-amber-50/40 to-stone-100 text-stone-900" data-pwa-sw="{{ url('/sw.js') }}">
    <header class="sticky top-0 z-50 border-b border-orange-500/30 bg-gradient-to-r from-stone-950 via-stone-900 to-stone-800 pt-[env(safe-area-inset-top)] shadow-md shadow-black/25">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('home') }}" class="group flex min-w-0 items-center gap-3 rounded-xl px-1 py-1.5 transition">
                        <span class="relative shrink-0 overflow-hidden rounded-lg border border-orange-300/60 bg-white shadow-md shadow-black/20 ring-1 ring-orange-200/30">
                            <img
                                src="{{ asset('brand/vsedetali-logo-full.png') }}"
                                alt="Логотип {{ $storeName }}"
                                class="h-11 w-16 object-cover object-center sm:h-12 sm:w-20 lg:h-14 lg:w-24"
                            >
                        </span>
                        <span class="min-w-0 leading-none">
                            <span class="block truncate text-[1.35rem] font-black tracking-[-0.02em] text-white transition group-hover:text-orange-200 sm:text-[1.5rem] lg:text-[1.68rem]">{{ $storeName }}</span>
                            <span class="mt-1.5 hidden text-[10.5px] font-semibold uppercase tracking-[0.12em] text-orange-200/90 sm:block">
                                От гайки до двигателя
                            </span>
                        </span>
                    </a>
                </div>

                <nav class="flex w-full flex-wrap items-center justify-between gap-x-3 gap-y-2 sm:w-auto sm:justify-end">
                    @livewire('storefront.cart-icon')
                    @auth
                        @if(Route::has('account.dashboard'))
                            <a href="{{ route('account.dashboard') }}" class="inline-flex items-center min-h-10 text-sm text-stone-300 transition hover:text-orange-300 max-[380px]:max-w-[9rem] max-[380px]:truncate" title="Личный кабинет">Личный кабинет</a>
                        @endif
                        @if(Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" class="inline-flex items-center">
                                @csrf
                                <button type="submit" class="min-h-10 px-1 text-sm text-stone-300 transition hover:text-orange-300">Выход</button>
                            </form>
                        @endif
                    @else
                        @if(Route::has('login'))
                            <a href="{{ route('login') }}" class="inline-flex items-center min-h-10 text-sm text-stone-300 transition hover:text-orange-300">Вход</a>
                        @endif
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center min-h-10 rounded-lg bg-orange-600 px-3 text-sm font-semibold text-white shadow-sm shadow-orange-600/30 transition hover:bg-orange-500">Регистрация</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full min-w-0 mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-900 break-words">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded bg-red-100 p-3 text-sm text-red-800 break-words">{{ session('error') }}</div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="mt-auto border-t border-orange-600/30 bg-stone-950 py-6 text-stone-400 sm:py-8 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between gap-6">
                <div class="min-w-0">
                    <div class="flex items-center gap-3">
                        <span class="relative shrink-0 overflow-hidden rounded-md border border-orange-300/45 bg-white ring-1 ring-orange-200/20">
                            <img
                                src="{{ asset('brand/vsedetali-logo-full.png') }}"
                                alt="Логотип {{ $storeName }}"
                                class="h-7 w-11 object-cover object-center"
                            >
                        </span>
                        <span class="font-semibold text-white">{{ $storeName }}</span>
                    </div>
                    <p class="mt-1 text-sm text-stone-500">Интернет-магазин автозапчастей</p>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm sm:text-base">
                    @if(Route::has('page.show'))
                        <a href="{{ route('page.show', ['slug' => 'delivery']) }}" class="transition hover:text-orange-300">Доставка</a>
                        <a href="{{ route('page.show', ['slug' => 'payment']) }}" class="transition hover:text-orange-300">Оплата</a>
                        <a href="{{ route('page.show', ['slug' => 'contacts']) }}" class="transition hover:text-orange-300">Контакты</a>
                    @endif
                </div>
            </div>
        </div>
    </footer>

    {{-- Чат с админом временно отключён на витрине (компонент не удалён). --}}
    {{-- @livewire('storefront.store-chat-widget') --}}
    @livewire('storefront.cart-drawer')
    @livewireScripts
    @include('partials.pwa-install-banner')
</body>
</html>
