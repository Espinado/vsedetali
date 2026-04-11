@extends('layouts.storefront')

@section('title', 'Вход')

@section('content')
<div class="mx-auto max-w-md min-w-0">
    <h1 class="mb-4 text-xl font-bold sm:mb-6 sm:text-2xl">Вход</h1>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Пароль</label>
            <input type="password" name="password" id="password" required autocomplete="current-password"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
        </div>
        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
            <label for="remember" class="ml-2 text-sm text-slate-600">Запомнить меня</label>
        </div>
        <div class="pt-2">
            <button type="submit" class="min-h-11 w-full rounded bg-orange-600 px-4 py-2.5 font-medium text-white hover:bg-orange-700">Войти</button>
        </div>
    </form>
    <p class="mt-4 text-center text-slate-600 text-sm">
        Нет аккаунта? <a href="{{ route('register') }}" class="text-orange-600 hover:underline">Регистрация</a>
    </p>
</div>
@endsection
