@extends('layouts.storefront')

@section('title', 'Оплата заказа #' . $order->id)

@section('content')
<div class="mx-auto max-w-2xl min-w-0">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm sm:p-8">
        <div class="mx-auto mb-6 h-16 w-16 rounded-full border-4 border-slate-200 border-t-orange-600 animate-spin"></div>

        <h1 class="mb-3 text-xl font-bold text-slate-900 sm:text-2xl">Идёт оформление заказа</h1>
        <p class="text-slate-600 mb-2">
            Заказ #{{ $order->id }} создан. Обрабатываем данные.
        </p>
        <p class="text-sm text-slate-500 mb-6">
            Подождите несколько секунд. Вы будете перенаправлены на страницу подтверждения заказа.
        </p>

        <div class="rounded-xl bg-slate-50 border border-slate-200 p-4 text-left text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Номер заказа</span>
                <span class="font-medium text-slate-900">#{{ $order->id }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-4">
                <span class="text-slate-500">Сумма</span>
                <span class="font-medium text-slate-900">{{ number_format((float) $order->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
            </div>
        </div>

        <div class="mt-6">
            <a href="{{ route('checkout.success', $order) }}" class="text-orange-600 hover:underline">
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
