@extends('layouts.storefront')

@section('title', 'Мои адреса')

@section('content')
<div class="mx-auto max-w-2xl min-w-0">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-bold sm:text-2xl">Мои адреса</h1>
        <a href="{{ route('account.addresses.create') }}" class="inline-flex min-h-11 items-center justify-center rounded bg-orange-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-orange-700 sm:shrink-0">Добавить адрес</a>
    </div>

    @if($addresses->isEmpty())
        <p class="text-slate-600 py-8">У вас пока нет сохранённых адресов.</p>
        <a href="{{ route('account.addresses.create') }}" class="inline-block px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">Добавить адрес</a>
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
                            <span class="inline-block mt-1 text-xs text-orange-600 font-medium">По умолчанию</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('account.addresses.edit', $address) }}" class="inline-flex min-h-10 items-center justify-center rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Изменить</a>
                        <form action="{{ route('account.addresses.destroy', $address) }}" method="POST" class="inline-flex" onsubmit="return confirm('Удалить этот адрес?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded border border-red-200 px-3 py-2 text-sm text-red-600 hover:bg-red-50">Удалить</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    <p class="mt-6">
        <a href="{{ route('account.dashboard') }}" class="text-orange-600 hover:underline">← В личный кабинет</a>
    </p>
</div>
@endsection
