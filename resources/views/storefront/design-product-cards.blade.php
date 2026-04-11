@extends('layouts.storefront')

@section('title', 'Макет карточек товара')

@section('content')
    @php
        $currency = \App\Models\Setting::get('currency', 'RUB');
        $gridMocks = [
            ['img' => true, 'brand' => 'Bosch', 'sku' => 'MOCK-001', 'name' => 'Фильтр масляный', 'price' => 890, 'in_stock' => true, 'total_stock' => 24, 'meta' => false],
            ['img' => true, 'brand' => 'Valeo', 'sku' => 'MOCK-002', 'name' => 'Очень длинное название детали для проверки переноса на две и три строки в сетке каталога и на узких экранах', 'price' => 12450.5, 'in_stock' => true, 'total_stock' => 4, 'meta' => true],
            ['img' => false, 'brand' => 'Febi', 'sku' => 'MOCK-003', 'name' => 'Стабилизатор поперечной устойчивости', 'price' => 2100, 'in_stock' => false, 'total_stock' => 0, 'meta' => false],
            ['img' => true, 'brand' => null, 'sku' => 'MOCK-004', 'name' => 'Без бренда в строке', 'price' => 330, 'in_stock' => true, 'total_stock' => 120, 'meta' => true],
            ['img' => true, 'brand' => 'Lemförder', 'sku' => 'MOCK-005', 'name' => 'Рычаг подвески передний нижний левый', 'price' => 6890, 'in_stock' => true, 'total_stock' => 1, 'meta' => false],
            ['img' => true, 'brand' => 'Mann', 'sku' => 'MOCK-006', 'name' => 'Воздушный фильтр', 'price' => 1560, 'in_stock' => true, 'total_stock' => 8, 'meta' => false, 'cross_caption' => 'По кроссу OEM 1K0129620'],
        ];
        $compactMocks = [
            ['img' => true, 'brand' => 'Sachs', 'sku' => 'CMP-01', 'name' => 'Амортизатор задний газовый', 'price' => 4520, 'in_stock' => true, 'total_stock' => 15, 'cross_caption' => null],
            ['img' => true, 'brand' => 'TRW', 'sku' => 'CMP-02', 'name' => 'Колодки передние с длинным названием для компактной строки поиска', 'price' => 2890, 'in_stock' => true, 'total_stock' => 2, 'cross_caption' => 'Аналог по номеру GDB1763'],
            ['img' => false, 'brand' => 'NK', 'sku' => 'CMP-03', 'name' => 'Сайлентблок', 'price' => 640, 'in_stock' => false, 'total_stock' => 0, 'cross_caption' => null],
            ['img' => true, 'brand' => 'Febi', 'sku' => 'CMP-04', 'name' => 'Втулка стабилизатора', 'price' => 410, 'in_stock' => true, 'total_stock' => 50, 'cross_caption' => null],
        ];
    @endphp

    <div class="mx-auto max-w-7xl space-y-12 px-3 pb-16 pt-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 shadow-sm">
            <strong class="font-semibold">Локальный макет.</strong>
            Страница доступна только при <code class="rounded bg-white/80 px-1 py-0.5 text-xs">APP_ENV=local</code>.
            Карточки ниже — статические имитации для проверки вёрстки; ссылки и кнопки не ведут в каталог.
        </div>

        <section class="space-y-4">
            <h1 class="text-2xl font-semibold text-stone-900">Карточки в сетке (стандарт)</h1>
            <p class="max-w-3xl text-sm text-slate-600">Шесть вариантов: короткое и длинное имя, «нет фото», под заказ, полоса аналогов/совместимости, подпись по кроссу.</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($gridMocks as $m)
                    <div class="card-store-product group flex min-h-0 min-w-0 flex-col">
                        <div class="storefront-card-media aspect-square w-full">
                            @if ($m['img'])
                                <img src="https://placehold.co/640x640/f97316/ffffff?text=Demo"
                                     alt=""
                                     width="640"
                                     height="640"
                                     class="transition duration-300 group-hover:scale-[1.03]">
                            @else
                                <span class="absolute inset-0 flex items-center justify-center text-sm text-slate-400">Нет фото</span>
                            @endif
                        </div>
                        <div class="p-4">
                            @if (! empty($m['cross_caption'] ?? null))
                                <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $m['cross_caption'] }}</p>
                            @endif
                            @if (! empty($m['brand']))
                                <p class="mb-0.5 text-xs text-slate-500">{{ $m['brand'] }}</p>
                            @endif
                            <p class="mb-0.5 font-mono text-xs text-slate-500">{{ $m['sku'] }}</p>
                            <h3 class="line-clamp-2 font-medium text-stone-900 transition group-hover:text-orange-800">{{ $m['name'] }}</h3>
                            <p class="mt-2 text-xl font-bold text-orange-800">{{ number_format($m['price'], 2) }} {{ $currency }}</p>
                            @if ($m['in_stock'])
                                <p class="mt-1 text-xs font-semibold text-emerald-700">
                                    @if ($m['total_stock'] < 10)
                                        В наличии ({{ $m['total_stock'] }})
                                    @else
                                        В наличии
                                    @endif
                                </p>
                            @else
                                <p class="mt-1 text-xs font-semibold text-amber-700">Под заказ</p>
                            @endif
                            @if ($m['meta'])
                                <div class="mt-3 space-y-1.5 rounded-xl border border-orange-100/80 bg-orange-50/40 p-3">
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">Аналоги:</span>
                                        <span class="font-mono">1234-AB, 5678-CD</span>
                                    </p>
                                    <p class="text-xs text-slate-600">
                                        <span class="font-medium text-slate-700">Совместимость:</span>
                                        Audi A4 (B8), VW Passat (B7)
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">Компактное превью (как в поиске)</h2>
            <div class="mx-auto max-w-3xl space-y-3">
                @foreach ($compactMocks as $m)
                    <div class="card-store-product group flex min-h-0 min-w-0 items-start gap-3 p-3 sm:gap-4 sm:p-4">
                        <div class="storefront-card-media h-16 w-16 shrink-0 rounded-lg sm:h-20 sm:w-20">
                            @if ($m['img'])
                                <img src="https://placehold.co/160x160/f97316/ffffff?text=D"
                                     alt=""
                                     width="160"
                                     height="160"
                                     class="transition duration-300 group-hover:scale-[1.03]">
                            @else
                                <span class="absolute inset-0 flex items-center justify-center text-[10px] text-slate-400">Нет фото</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            @if (! empty($m['cross_caption']))
                                <p class="mb-1 rounded-md bg-orange-50/90 px-1.5 py-1 text-[10px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">{{ $m['cross_caption'] }}</p>
                            @endif
                            @if (! empty($m['brand']))
                                <p class="mb-0.5 text-xs text-slate-500">{{ $m['brand'] }}</p>
                            @endif
                            <p class="mb-0.5 font-mono text-[11px] text-slate-500">{{ $m['sku'] }}</p>
                            <h3 class="line-clamp-2 text-sm font-medium leading-snug text-stone-900 transition group-hover:text-orange-800">{{ $m['name'] }}</h3>
                            <p class="mt-1.5 text-base font-bold text-orange-800 sm:text-lg">{{ number_format($m['price'], 2) }} {{ $currency }}</p>
                            @if ($m['in_stock'])
                                <p class="mt-0.5 text-[11px] font-semibold text-emerald-700">
                                    @if ($m['total_stock'] < 10)
                                        В наличии ({{ $m['total_stock'] }})
                                    @else
                                        В наличии
                                    @endif
                                </p>
                            @else
                                <p class="mt-0.5 text-[11px] font-semibold text-amber-700">Под заказ</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold text-stone-900">Карточка выбора (широкая, с футером)</h2>
            <p class="max-w-3xl text-sm text-slate-600">Макет футера с полем количества и CTA — без Livewire.</p>
            <div class="card-store-product group flex h-full min-h-0 flex-col overflow-hidden md:grid md:grid-cols-[minmax(11rem,15.5rem)_1fr] md:gap-0 lg:grid-cols-[minmax(12rem,17rem)_1fr]">
                <div class="storefront-card-media h-40 w-full shrink-0 sm:h-44 md:h-48 lg:h-52">
                    <img src="https://placehold.co/480x480/ea580c/ffffff?text=Featured"
                         alt=""
                         width="480"
                         height="480"
                         class="transition duration-300 group-hover:scale-[1.02]">
                </div>
                <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div class="block min-h-0 flex-1 px-4 py-3 sm:px-5 sm:py-4">
                        <p class="mb-2 rounded-lg bg-orange-50/90 px-2 py-1.5 text-[11px] font-medium leading-snug text-orange-900 ring-1 ring-orange-100/80">Выбрано по фильтру: Audi A4 · 2012</p>
                        <p class="mb-0.5 text-xs text-slate-500">Continental</p>
                        <p class="mb-0.5 font-mono text-xs text-slate-500">FEAT-MOCK-01</p>
                        <h3 class="line-clamp-2 font-medium text-stone-900 sm:line-clamp-3">Ремень ГРМ с роликами комплект для проверки многострочного заголовка в широкой карточке</h3>
                        <p class="mt-2 text-lg font-bold text-orange-800 sm:text-xl">12 490,00 {{ $currency }}</p>
                        <p class="mt-1 text-xs font-semibold text-emerald-700">В наличии (6)</p>
                        <div class="mt-3 space-y-1 rounded-lg border border-orange-100/80 bg-orange-50/40 p-2.5 sm:p-3">
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Аналоги:</span>
                                <span class="font-mono">CONTITECH CT987, INA 530055010</span>
                            </p>
                            <p class="text-xs text-slate-600">
                                <span class="font-medium text-slate-700">Совместимость:</span>
                                Audi A4 (B8) 2.0 TDI
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-orange-100/90 bg-gradient-to-b from-orange-50/40 to-white px-4 py-3 sm:px-5 sm:py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                            <div class="flex items-center gap-2">
                                <span class="shrink-0 text-sm text-slate-600">Кол-во:</span>
                                <input type="number" readonly value="1" class="h-11 w-24 cursor-not-allowed rounded-lg border-slate-300 bg-white text-center text-sm opacity-80 shadow-sm" aria-label="Количество (макет)">
                            </div>
                            <button type="button" class="btn-store-cta pointer-events-none w-full opacity-90 sm:w-auto" tabindex="-1">В корзину</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($demo)
            <section class="space-y-4 border-t border-orange-100/80 pt-10">
                <h2 class="text-xl font-semibold text-stone-900">Живой компонент из БД</h2>
                <p class="max-w-3xl text-sm text-slate-600">
                    Первый активный товар: <span class="font-mono text-stone-800">{{ $demo->sku }}</span>.
                    Ниже — настоящие <code class="rounded bg-stone-100 px-1 text-xs">x-storefront-product-card</code> и кнопка корзины.
                </p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (range(1, 6) as $i)
                        <x-storefront-product-card :product="$demo" />
                    @endforeach
                </div>
                <h3 class="pt-4 text-lg font-medium text-stone-900">Компакт (тот же товар)</h3>
                <div class="mx-auto max-w-3xl space-y-3">
                    @foreach (range(1, 3) as $i)
                        <x-storefront-product-card :product="$demo" compact-preview :cross-caption="$i === 2 ? 'Подпись по кроссу для превью' : null" />
                    @endforeach
                </div>
                <h3 class="pt-4 text-lg font-medium text-stone-900">Выбранный товар (слот + корзина)</h3>
                <div class="max-w-4xl">
                    <x-storefront-product-card :product="$demo" selected-vehicle-label="Audi A4 (B8) 2.0 TDI">
                        <x-slot name="cart">
                            @livewire('storefront.add-to-cart-button', ['product' => $demo], key('design-cart-'.$demo->id))
                        </x-slot>
                    </x-storefront-product-card>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-stone-200 bg-stone-50/80 px-4 py-4 text-sm text-slate-700">
                В базе нет активного товара — блок с живым компонентом скрыт. Статические макеты выше достаточны для проверки дизайна; для интерактива добавьте хотя бы один активный товар.
            </section>
        @endif
    </div>
@endsection
