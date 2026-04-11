@extends('layouts.storefront')

@section('title', 'Мои заказы')

@section('content')
<div class="mx-auto max-w-4xl min-w-0">
    <h1 class="mb-4 text-xl font-bold sm:mb-6 sm:text-2xl">Мои заказы</h1>

    @if($orders->isEmpty())
        <p class="text-slate-600 py-8">У вас пока нет заказов.</p>
        <a href="{{ route('home') }}" class="btn-store-cta-sm min-h-11 px-6">На главную</a>
    @else
        <ul class="divide-y divide-slate-200 bg-white rounded-lg border border-slate-200 overflow-hidden">
            @foreach($orders as $order)
                <li class="p-4 hover:bg-slate-50">
                    <a href="{{ route('account.orders.show', $order) }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <span class="font-medium text-slate-900">Заказ #{{ $order->id }}</span>
                            <span class="ml-2 text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $order->status->color ?? '#e2e8f0' }}30; color: {{ $order->status->color ?? '#64748b' }};">
                                {{ $order->status->name }}
                            </span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $order->payment_status_color }}20; color: {{ $order->payment_status_color }};">
                                {{ $order->payment_status_label }}
                            </span>
                            <span class="font-semibold text-slate-900">{{ number_format((float) $order->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4 overflow-x-auto pb-2">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
