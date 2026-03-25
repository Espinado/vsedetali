@extends('layouts.storefront')

@section('title', 'Заказ успешно оформлен')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <div class="text-center">
            <h1 class="text-2xl font-bold text-slate-900 mb-3">Заказ успешно оформлен</h1>
            <p class="text-slate-600">
                Заказ #{{ $order->id }} принят. Он сохранён в вашем личном кабинете. Статус оплаты обновится после подтверждения заказа менеджером.
            </p>
        </div>

        <div class="mt-8 rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Статус заказа</span>
                <span class="font-medium text-slate-900">{{ $order->status->name ?? 'Новый' }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-4">
                <span class="text-slate-500">Сумма</span>
                <span class="font-medium text-slate-900">{{ number_format((float) $order->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-4">
                <span class="text-slate-500">Статус оплаты</span>
                <span class="font-medium" style="color: {{ $order->payment_status_color }};">{{ $order->payment_status_label }}</span>
            </div>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('account.orders.show', $order) }}" class="inline-flex justify-center px-5 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                Перейти к заказу
            </a>
            <a href="{{ route('catalog') }}" class="inline-flex justify-center px-5 py-3 border border-slate-300 rounded-lg hover:bg-slate-50 font-medium text-slate-700">
                Вернуться в каталог
            </a>
        </div>
    </div>
</div>
@endsection
