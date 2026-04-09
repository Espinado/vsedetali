@extends('layouts.storefront')

@section('title', 'Установка пароля')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-2">Пароль для входа в панель</h1>
    <p class="text-slate-600 text-sm mb-6">{{ $staff->name }}, задайте пароль и подтвердите его.</p>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('staff.invite.update', ['token' => $token]) }}" class="space-y-4 bg-white rounded-lg border border-slate-200 p-6">
        @csrf
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
            <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 font-medium">Сохранить и войти</button>
        </div>
    </form>
</div>
@endsection
