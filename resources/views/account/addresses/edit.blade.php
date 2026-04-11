@extends('layouts.storefront')

@section('title', 'Изменить адрес')

@section('content')
<div class="mx-auto max-w-md min-w-0">
    <h1 class="mb-4 text-xl font-bold sm:mb-6 sm:text-2xl">Изменить адрес</h1>

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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        <div class="flex flex-col gap-2 pt-2 sm:flex-row">
            <button type="submit" class="min-h-11 w-full rounded bg-orange-600 px-4 py-2.5 text-white hover:bg-orange-700 sm:w-auto">Сохранить</button>
            <a href="{{ route('account.addresses.index') }}" class="inline-flex min-h-11 w-full items-center justify-center rounded border border-slate-300 px-4 py-2.5 hover:bg-slate-50 sm:w-auto">Отмена</a>
        </div>
    </form>
</div>
@endsection
