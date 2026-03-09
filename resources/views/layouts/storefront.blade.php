<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $title ?? 'VSEDETALI') — {{ \App\Models\Setting::get('store_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14 md:h-16">
                <a href="{{ route('home') }}" class="font-semibold text-xl text-slate-800">VSEDETALI</a>
                <nav class="flex items-center gap-4">
                    <a href="{{ route('catalog') }}" class="text-slate-600 hover:text-slate-900">Каталог</a>
                    @livewire('storefront.cart-icon')
                    @auth
                        @if(Route::has('account.dashboard'))
                            <a href="{{ route('account.dashboard') }}" class="text-slate-600 hover:text-slate-900">Личный кабинет</a>
                        @endif
                        @if(Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit" class="text-slate-600 hover:text-slate-900">Выход</button>
                            </form>
                        @endif
                    @else
                        @if(Route::has('login'))
                            <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Вход</a>
                        @endif
                        @if(Route::has('register'))
                            <a href="{{ route('register') }}" class="text-slate-600 hover:text-slate-900">Регистрация</a>
                        @endif
                    @endauth
                </nav>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="bg-slate-800 text-slate-300 py-8 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between gap-4">
                <div>
                    <span class="font-semibold text-white">VSEDETALI</span>
                    <p class="text-sm mt-1">Интернет-магазин автозапчастей</p>
                </div>
                <div class="flex gap-6">
                    @if(Route::has('page.show'))
                        <a href="{{ route('page.show', ['slug' => 'delivery']) }}" class="hover:text-white">Доставка</a>
                        <a href="{{ route('page.show', ['slug' => 'payment']) }}" class="hover:text-white">Оплата</a>
                        <a href="{{ route('page.show', ['slug' => 'contacts']) }}" class="hover:text-white">Контакты</a>
                    @endif
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
