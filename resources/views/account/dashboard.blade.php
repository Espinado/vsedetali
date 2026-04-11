@extends('layouts.storefront')

@section('title', 'Личный кабинет')

@section('content')
<div class="mx-auto max-w-4xl min-w-0">
    <h1 class="mb-4 text-xl font-bold sm:mb-6 sm:text-2xl">Личный кабинет</h1>
    <p class="text-slate-600 mb-8">Здравствуйте, {{ auth()->user()->name }}.</p>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 md:grid-cols-3 md:gap-6">
        <a href="{{ route('account.profile.edit') }}" class="block rounded-lg border border-orange-100/90 bg-white p-4 transition hover:border-orange-300 hover:shadow-md hover:shadow-orange-950/5">
            <h2 class="font-semibold text-slate-900 mb-1">Профиль</h2>
            <p class="text-sm text-slate-600">Имя, email, телефон, пароль</p>
        </a>
        <a href="{{ route('account.orders.index') }}" class="block rounded-lg border border-orange-100/90 bg-white p-4 transition hover:border-orange-300 hover:shadow-md hover:shadow-orange-950/5">
            <h2 class="font-semibold text-slate-900 mb-1">Мои заказы</h2>
            <p class="text-sm text-slate-600">История заказов и статусы</p>
        </a>
        <a href="{{ route('account.addresses.index') }}" class="block rounded-lg border border-orange-100/90 bg-white p-4 transition hover:border-orange-300 hover:shadow-md hover:shadow-orange-950/5 sm:col-span-2 md:col-span-1">
            <h2 class="font-semibold text-slate-900 mb-1">Адреса доставки</h2>
            <p class="text-sm text-slate-600">Управление адресами</p>
        </a>
    </div>

    @if($recentOrders->isNotEmpty())
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-semibold text-slate-900">Последние заказы</h2>
                <a href="{{ route('account.orders.index') }}" class="inline-flex min-h-9 items-center text-sm text-orange-600 hover:underline">Все заказы →</a>
            </div>
            <ul class="divide-y divide-slate-200">
                @foreach($recentOrders as $order)
                    <li class="p-4 hover:bg-slate-50">
                        <a href="{{ route('account.orders.show', $order) }}" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <span class="font-medium text-slate-900">Заказ #{{ $order->id }}</span>
                            <span class="text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $order->status->color ?? '#e2e8f0' }}30; color: {{ $order->status->color ?? '#64748b' }};">
                                {{ $order->status->name }}
                            </span>
                            <span class="font-semibold text-slate-900">{{ number_format((float) $order->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
