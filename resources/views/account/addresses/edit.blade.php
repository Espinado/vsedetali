@extends('layouts.storefront')

@section('title', 'Изменить адрес')

@section('content')
<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-6">Изменить адрес</h1>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('account.addresses.update', $address) }}" class="space-y-4 bg-white rounded-lg border border-slate-200 p-6">
        @csrf
        @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Получатель / Название</label>
            <input type="text" name="name" id="name" value="{{ old('name', $address->name) }}" required class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="full_address" class="block text-sm font-medium text-slate-700 mb-1">Адрес *</label>
            <input type="text" name="full_address" id="full_address" value="{{ old('full_address', $address->full_address) }}" required class="w-full rounded border-slate-300">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="city" class="block text-sm font-medium text-slate-700 mb-1">Город *</label>
                <input type="text" name="city" id="city" value="{{ old('city', $address->city) }}" required class="w-full rounded border-slate-300">
            </div>
            <div>
                <label for="postcode" class="block text-sm font-medium text-slate-700 mb-1">Индекс</label>
                <input type="text" name="postcode" id="postcode" value="{{ old('postcode', $address->postcode) }}" class="w-full rounded border-slate-300">
            </div>
        </div>
        <div>
            <label for="region" class="block text-sm font-medium text-slate-700 mb-1">Регион</label>
            <input type="text" name="region" id="region" value="{{ old('region', $address->region) }}" class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="country" class="block text-sm font-medium text-slate-700 mb-1">Страна *</label>
            <input type="text" name="country" id="country" value="{{ old('country', $address->country) }}" maxlength="2" required class="w-full rounded border-slate-300">
        </div>
        <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
            <input type="text" name="phone" id="phone" value="{{ old('phone', $address->phone) }}" class="w-full rounded border-slate-300">
        </div>
        <div class="flex items-center">
            <input type="hidden" name="is_default" value="0">
            <input type="checkbox" name="is_default" id="is_default" value="1" {{ old('is_default', $address->is_default) ? 'checked' : '' }} class="rounded border-slate-300">
            <label for="is_default" class="ml-2 text-sm text-slate-600">Использовать по умолчанию при оформлении заказа</label>
        </div>
        <div class="pt-2 flex gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Сохранить</button>
            <a href="{{ route('account.addresses.index') }}" class="px-4 py-2 border border-slate-300 rounded hover:bg-slate-50">Отмена</a>
        </div>
    </form>
</div>
@endsection
