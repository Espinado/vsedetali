<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.partials.seo-head')
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col min-w-0 bg-gradient-to-b from-orange-50/90 via-amber-50/40 to-stone-100 text-stone-900">
    <header class="sticky top-0 z-50 border-b border-orange-950/30 bg-gradient-to-r from-stone-950 via-stone-900 to-stone-800 pt-[env(safe-area-inset-top)] shadow-md shadow-black/25">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('home') }}" class="truncate min-w-0 text-lg font-bold text-white transition hover:text-orange-200 sm:text-xl">{{ $storeName }}</a>
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

    <footer class="mt-auto border-t border-orange-950/40 bg-stone-950 py-6 text-stone-400 sm:py-8 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between gap-6">
                <div class="min-w-0">
                    <span class="font-semibold text-white">{{ $storeName }}</span>
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
</body>
</html>
