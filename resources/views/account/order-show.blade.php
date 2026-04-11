@extends('layouts.storefront')

@section('title', 'Заказ #' . $order->id)

@section('content')
<div class="mx-auto max-w-4xl min-w-0">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-bold sm:text-2xl">Заказ #{{ $order->id }}</h1>
        <a href="{{ route('account.orders.index') }}" class="inline-flex min-h-10 items-center text-sm text-orange-600 hover:underline sm:text-base">← К списку заказов</a>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2 sm:gap-3">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
              style="background-color: {{ $order->status->color ?? '#e2e8f0' }}20; color: {{ $order->status->color ?? '#64748b' }};">
            {{ $order->status->name }}
        </span>
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
              style="background-color: {{ $order->payment_status_color }}20; color: {{ $order->payment_status_color }};">
            {{ $order->payment_status_label }}
        </span>
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium"
              style="background-color: {{ $order->shipment_status_color }}20; color: {{ $order->shipment_status_color }};">
            {{ $order->shipment_status_label }}
        </span>
    </div>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
            <h2 class="mb-3 text-lg font-semibold">Контактные данные</h2>
            <p class="text-slate-700">{{ $order->customer_name }}</p>
            <p class="text-slate-600 text-sm">{{ $order->customer_email }}</p>
            @if ($order->customer_phone)
                <p class="text-slate-600 text-sm">{{ $order->customer_phone }}</p>
            @endif
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
            <h2 class="mb-3 text-lg font-semibold">Доставка</h2>
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

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 p-4 text-lg font-semibold">Товары</h2>
        <div class="-mx-1 overflow-x-auto sm:mx-0 [-webkit-overflow-scrolling:touch]">
        <table class="w-full min-w-[36rem] text-sm sm:min-w-full">
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
                        <td class="px-4 py-3 text-right">{{ number_format((float) $item->price, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $item->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="space-y-1 border-t border-slate-200 bg-slate-50 p-4 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-600">Товары</span>
                <span>{{ number_format((float) $order->subtotal, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-600">Доставка</span>
                <span>{{ number_format((float) $order->shipping_cost, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
            </div>
            <div class="flex justify-between font-semibold text-base pt-2">
                <span>Итого</span>
                <span>{{ number_format((float) $order->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
            </div>
        </div>
    </div>

    @if ($order->comment)
        <div class="mt-6 p-4 bg-slate-50 rounded-lg">
            <h3 class="font-medium text-slate-700 mb-1">Комментарий к заказу</h3>
            <p class="text-slate-600 text-sm">{{ $order->comment }}</p>
        </div>
    @endif

    <div class="mt-6 p-4 bg-slate-50 rounded-lg">
        <h3 class="font-medium text-slate-700 mb-2">Отгрузка</h3>
        <div class="space-y-1 text-sm text-slate-600">
            <p>Статус: <span class="font-medium" style="color: {{ $order->shipment_status_color }};">{{ $order->shipment_status_label }}</span></p>
            <p>Способ доставки: {{ $order->latestShipment?->shippingMethod?->name ?? $order->shippingMethod->name ?? '—' }}</p>
            <p>Трек-номер: {{ $order->latestShipment?->tracking_number ?: '—' }}</p>
            <p>Дата отгрузки: {{ $order->latestShipment?->shipped_at?->format('d.m.Y H:i') ?: '—' }}</p>
        </div>
    </div>

    <div class="mt-6 text-sm text-slate-500 space-y-1">
        <p>Оплата: {{ $order->paymentMethod->name ?? '—' }}</p>
        @if ($order->latestPayment?->paid_at)
            <p>Оплачено: {{ $order->latestPayment->paid_at->format('d.m.Y H:i') }}</p>
        @endif
    </div>
</div>
@endsection
