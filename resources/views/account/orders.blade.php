@extends('layouts.storefront')

@section('title', 'Мои заказы')

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Мои заказы</h1>

    @if($orders->isEmpty())
        <p class="text-slate-600 py-8">У вас пока нет заказов.</p>
        <a href="{{ route('catalog') }}" class="inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Перейти в каталог</a>
    @else
        <ul class="divide-y divide-slate-200 bg-white rounded-lg border border-slate-200 overflow-hidden">
            @foreach($orders as $order)
                <li class="p-4 hover:bg-slate-50">
                    <a href="{{ route('account.orders.show', $order) }}" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <span class="font-medium text-slate-900">Заказ #{{ $order->id }}</span>
                            <span class="ml-2 text-sm text-slate-500">{{ $order->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
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
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
