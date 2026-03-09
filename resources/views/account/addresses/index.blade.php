@extends('layouts.storefront')

@section('title', 'Мои адреса')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Мои адреса</h1>
        <a href="{{ route('account.addresses.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm font-medium">Добавить адрес</a>
    </div>

    @if($addresses->isEmpty())
        <p class="text-slate-600 py-8">У вас пока нет сохранённых адресов.</p>
        <a href="{{ route('account.addresses.create') }}" class="inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Добавить адрес</a>
    @else
        <ul class="space-y-4">
            @foreach($addresses as $address)
                <li class="bg-white rounded-lg border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <p class="font-medium text-slate-900">{{ $address->name ?: 'Адрес' }}</p>
                        <p class="text-slate-600 text-sm">{{ $address->full_address }}, {{ $address->city }}{{ $address->postcode ? ', ' . $address->postcode : '' }}</p>
                        @if($address->phone)
                            <p class="text-slate-500 text-sm">{{ $address->phone }}</p>
                        @endif
                        @if($address->is_default)
                            <span class="inline-block mt-1 text-xs text-indigo-600 font-medium">По умолчанию</span>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="px-3 py-1.5 text-sm border border-slate-300 rounded hover:bg-slate-50">Изменить</a>
                        <form action="{{ route('account.addresses.destroy', $address) }}" method="POST" class="inline" onsubmit="return confirm('Удалить этот адрес?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded hover:bg-red-50">Удалить</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="mt-6">
        <a href="{{ route('account.dashboard') }}" class="text-indigo-600 hover:underline">← В личный кабинет</a>
    </p>
</div>
@endsection
