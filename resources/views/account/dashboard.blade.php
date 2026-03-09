@extends('layouts.storefront')

@section('title', 'Личный кабинет')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Личный кабинет</h1>
    <p class="text-slate-600 mb-8">Здравствуйте, {{ auth()->user()->name }}.</p>

    <div class="grid gap-6 md:grid-cols-3 mb-8">
        <a href="{{ route('account.profile.edit') }}" class="block p-4 bg-white rounded-lg border border-slate-200 hover:border-indigo-300 hover:shadow-sm transition">
            <h2 class="font-semibold text-slate-900 mb-1">Профиль</h2>
            <p class="text-sm text-slate-600">Имя, email, телефон, пароль</p>
        </a>
        <a href="{{ route('account.orders.index') }}" class="block p-4 bg-white rounded-lg border border-slate-200 hover:border-indigo-300 hover:shadow-sm transition">
            <h2 class="font-semibold text-slate-900 mb-1">Мои заказы</h2>
            <p class="text-sm text-slate-600">История заказов и статусы</p>
        </a>
        <a href="{{ route('account.addresses.index') }}" class="block p-4 bg-white rounded-lg border border-slate-200 hover:border-indigo-300 hover:shadow-sm transition">
            <h2 class="font-semibold text-slate-900 mb-1">Адреса доставки</h2>
            <p class="text-sm text-slate-600">Управление адресами</p>
        </a>
    </div>

    @if($recentOrders->isNotEmpty())
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Последние заказы</h2>
                <a href="{{ route('account.orders.index') }}" class="text-sm text-indigo-600 hover:underline">Все заказы →</a>
            </div>
            <ul class="divide-y divide-slate-200">
                @foreach($recentOrders as $order)
                    <li class="p-4 hover:bg-slate-50">
                        <a href="{{ route('account.orders.show', $order) }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <span class="font-medium text-slate-900">Заказ #{{ $order->id }}</span>
                            <span class="text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $order->status->color ?? '#e2e8f0' }}30; color: {{ $order->status->color ?? '#64748b' }};">
                                {{ $order->status->name }}
                            </span>
                            <span class="font-semibold text-slate-900">{{ number_format((float) $order->total, 2) }} €</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
