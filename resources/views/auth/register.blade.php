@extends('layouts.storefront')

@section('title', 'Регистрация')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-6">Регистрация</h1>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4 bg-white rounded-lg border border-slate-200 p-6">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Имя</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Пароль</label>
            <input type="password" name="password" id="password" required autocomplete="new-password"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Подтверждение пароля</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                   class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="pt-2">
            <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 font-medium">Зарегистрироваться</button>
        </div>
    </form>
    <p class="mt-4 text-center text-slate-600 text-sm">
        Уже есть аккаунт? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Вход</a>
    </p>
</div>
@endsection
