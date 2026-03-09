@extends('layouts.storefront')

@section('title', 'Редактирование профиля')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-6">Редактирование профиля</h1>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.profile.update') }}" class="space-y-4 bg-white rounded-lg border border-slate-200 p-6">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Имя</label>
            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Новый пароль</label>
            <input type="password" name="password" id="password" class="w-full rounded border-slate-300" autocomplete="new-password">
            <p class="text-xs text-slate-500 mt-1">Оставьте пустым, если не меняете пароль.</p>
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Подтверждение пароля</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="w-full rounded border-slate-300" autocomplete="new-password">
        </div>
        <div class="pt-2 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Сохранить</button>
            <a href="{{ route('account.dashboard') }}" class="px-4 py-2 border border-slate-300 rounded hover:bg-slate-50">Отмена</a>
        </div>
    </form>
</div>
@endsection
