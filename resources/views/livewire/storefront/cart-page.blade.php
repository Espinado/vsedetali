<div>
    <h1 class="text-2xl font-bold text-slate-900 mb-6">Корзина</h1>

    @if($this->items->isEmpty())
        <p class="text-slate-600 py-12">В корзине пока ничего нет.</p>
        <a href="{{ route('catalog') }}" class="inline-block px-6 py-3 bg-slate-800 text-white rounded-lg hover:bg-slate-700">Перейти в каталог</a>
    @else
        <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
            <ul class="divide-y divide-slate-200">
                @foreach($this->items as $item)
                    <li class="flex flex-col sm:flex-row gap-4 p-4 sm:items-center">
                        <a href="{{ route('product.show', $item->product) }}" class="shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-slate-100 overflow-hidden flex items-center justify-center">
                            @if($item->product->mainImage?->storage_url)
                                <img src="{{ $item->product->mainImage->storage_url }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-slate-400 text-xs">Нет фото</span>
                            @endif
                        </a>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('product.show', $item->product) }}" class="font-medium text-slate-800 hover:text-slate-600">
                                {{ $item->product->name }}
                            </a>
                            <p class="text-sm text-slate-500">{{ $item->product->sku }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-1">
                                <input type="number"
                                       min="1"
                                       max="99"
                                       value="{{ $item->quantity }}"
                                       wire:change="updateQuantity({{ $item->id }}, $event.target.value)"
                                       class="w-16 rounded border-slate-300 shadow-sm text-sm text-center">
                            </div>
                            <span class="w-24 text-right font-medium text-slate-900">
                                {{ number_format($item->price * $item->quantity, 2) }} {{ \App\Models\Setting::get('currency', 'EUR') }}
                            </span>
                            <button type="button"
                                    wire:click="removeItem({{ $item->id }})"
                                    wire:confirm="Удалить товар из корзины?"
                                    class="text-slate-400 hover:text-red-600 p-1"
                                    title="Удалить">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <p class="text-lg font-semibold text-slate-900">
                Итого: {{ number_format($this->subtotal, 2) }} {{ \App\Models\Setting::get('currency', 'EUR') }}
            </p>
            <a href="{{ route('checkout') }}" class="inline-block px-6 py-3 bg-slate-800 text-white rounded-lg hover:bg-slate-700 text-center font-medium">
                Оформить заказ
            </a>
        </div>
    @endif
</div>
