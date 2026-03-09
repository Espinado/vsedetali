@extends('layouts.storefront')

@section('title', 'Оплата заказа #' . $order->id)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
        <div class="mx-auto mb-6 h-16 w-16 rounded-full border-4 border-slate-200 border-t-indigo-600 animate-spin"></div>

        <h1 class="text-2xl font-bold text-slate-900 mb-3">Обрабатываем оплату</h1>
        <p class="text-slate-600 mb-2">
            Заказ #{{ $order->id }} создан. Имитируем успешную оплату через платёжный шлюз.
        </p>
        <p class="text-sm text-slate-500 mb-6">
            Пожалуйста, подождите несколько секунд. Затем вы будете перенаправлены на страницу успешного оформления.
        </p>

        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-left text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Номер заказа</span>
                <span class="font-medium text-slate-900">#{{ $order->id }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-4">
                <span class="text-slate-500">Сумма</span>
                <span class="font-medium text-slate-900">{{ number_format((float) $order->total, 2) }} €</span>
            </div>
            <div class="mt-2 flex justify-between gap-4">
                <span class="text-slate-500">Способ оплаты</span>
                <span class="font-medium text-slate-900">{{ $order->paymentMethod->name ?? 'Онлайн-оплата' }}</span>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('checkout.success', $order) }}" class="text-indigo-600 hover:underline">
                Если редирект не сработал, нажмите здесь
            </a>
        </div>
    </div>
</div>

<script>
    setTimeout(function () {
        window.location.href = @json(route('checkout.success', $order));
    }, 2500);
</script>
@endsection
