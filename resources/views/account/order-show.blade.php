@extends('layouts.storefront')

@section('title', 'Заказ #' . $order->id)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold">Заказ #{{ $order->id }}</h1>
        <a href="{{ route('account.orders.index') }}" class="text-indigo-600 hover:underline">← К списку заказов</a>
    </div>

    <div class="mb-4 inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
         style="background-color: {{ $order->status->color ?? '#e2e8f0' }}20; color: {{ $order->status->color ?? '#64748b' }};">
        {{ $order->status->name }}
    </div>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-lg font-semibold mb-3">Контактные данные</h2>
            <p class="text-slate-700">{{ $order->customer_name }}</p>
            <p class="text-slate-600 text-sm">{{ $order->customer_email }}</p>
            @if ($order->customer_phone)
                <p class="text-slate-600 text-sm">{{ $order->customer_phone }}</p>
            @endif
        </div>
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="text-lg font-semibold mb-3">Доставка</h2>
            <p class="text-slate-700 font-medium">{{ $order->shippingMethod->name ?? '—' }}</p>
            @php $addr = $order->shippingAddress(); @endphp
            @if ($addr)
                <p class="text-slate-600 text-sm mt-1">{{ $addr->name }}</p>
                <p class="text-slate-600 text-sm">{{ $addr->full_address }}, {{ $addr->city }}{{ $addr->postcode ? ', ' . $addr->postcode : '' }}</p>
                @if ($addr->phone)
                    <p class="text-slate-600 text-sm">{{ $addr->phone }}</p>
                @endif
            @endif
        </div>
    </div>

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <h2 class="text-lg font-semibold p-4 border-b border-slate-200">Товары</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium text-slate-700">Товар</th>
                    <th class="px-4 py-3 font-medium text-slate-700 text-right">Кол-во</th>
                    <th class="px-4 py-3 font-medium text-slate-700 text-right">Цена</th>
                    <th class="px-4 py-3 font-medium text-slate-700 text-right">Сумма</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @foreach ($order->orderItems as $item)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium">{{ $item->product_name }}</span>
                            @if ($item->sku)
                                <span class="text-slate-500 block text-xs">Артикул: {{ $item->sku }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">{{ $item->quantity }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $item->price, 2) }} €</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $item->total, 2) }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4 border-t border-slate-200 bg-slate-50 space-y-1 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-600">Товары</span>
                <span>{{ number_format((float) $order->subtotal, 2) }} €</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Доставка</span>
                <span>{{ number_format((float) $order->shipping_cost, 2) }} €</span>
            </div>
            <div class="flex justify-between font-semibold text-base pt-2">
                <span>Итого</span>
                <span>{{ number_format((float) $order->total, 2) }} €</span>
            </div>
        </div>
    </div>

    @if ($order->comment)
        <div class="mt-6 p-4 bg-slate-50 rounded-lg">
            <h3 class="font-medium text-slate-700 mb-1">Комментарий к заказу</h3>
            <p class="text-slate-600 text-sm">{{ $order->comment }}</p>
        </div>
    @endif

    <p class="mt-6 text-slate-500 text-sm">Оплата: {{ $order->paymentMethod->name ?? '—' }}</p>
</div>
@endsection
