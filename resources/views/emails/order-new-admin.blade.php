<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; line-height: 1.5; color: #334155; max-width: 640px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 1.25rem; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-weight: 600; }
        .total { font-weight: 700; font-size: 1.1rem; }
        .muted { color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <h1>Новый заказ #{{ $order->id }}</h1>
    <p><strong>Покупатель:</strong> {{ $order->customer_name }}</p>
    <p><strong>Email:</strong> <a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></p>
    @if($order->customer_phone)
        <p><strong>Телефон:</strong> {{ $order->customer_phone }}</p>
    @endif
    <p><strong>Статус:</strong> {{ $order->status->name ?? '—' }}</p>

    <h2>Состав заказа</h2>
    <table>
        <thead>
            <tr>
                <th>Товар</th>
                <th>Кол-во</th>
                <th>Цена</th>
                <th>Сумма</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product_name }} @if($item->sku)<span class="muted">({{ $item->sku }})</span>@endif</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->price, 2) }} €</td>
                    <td>{{ number_format((float) $item->total, 2) }} €</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Товары:</strong> {{ number_format((float) $order->subtotal, 2) }} €</p>
    <p><strong>Доставка:</strong> {{ number_format((float) $order->shipping_cost, 2) }} € ({{ $order->shippingMethod->name ?? '—' }})</p>
    <p class="total">Итого: {{ number_format((float) $order->total, 2) }} €</p>
    <p><strong>Оплата:</strong> {{ $order->paymentMethod->name ?? '—' }}</p>

    @php $addr = $order->shippingAddress(); @endphp
    @if($addr)
        <h2>Адрес доставки</h2>
        <p>{{ $addr->name }}<br>
        {{ $addr->full_address }}<br>
        {{ $addr->city }}{{ $addr->postcode ? ', ' . $addr->postcode : '' }}<br>
        @if($addr->phone){{ $addr->phone }}@endif</p>
    @endif

    @if($order->comment)
        <p><strong>Комментарий покупателя:</strong> {{ $order->comment }}</p>
    @endif

    <p class="muted">Письмо отправлено автоматически из магазина.</p>
</body>
</html>
