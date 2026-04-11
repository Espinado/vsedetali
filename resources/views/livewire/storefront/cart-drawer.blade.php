<div>
    @if($open)
        <div class="fixed inset-0 z-[70]">
            <div class="absolute inset-0 bg-slate-900/40" wire:click="closeDrawer"></div>

            <div class="absolute right-0 top-0 flex h-full max-h-[100dvh] w-full max-w-md min-h-0 flex-col bg-white pb-[env(safe-area-inset-bottom)] shadow-2xl shadow-orange-950/15">
                <div class="flex items-center justify-between border-b border-orange-100 bg-gradient-to-r from-orange-50/80 to-amber-50/40 px-4 py-3 sm:px-5 sm:py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Корзина</h2>
                        <p class="text-sm text-slate-500">
                            {{ $this->items->sum('quantity') }} позиций
                        </p>
                    </div>

                    <button type="button" wire:click="closeDrawer" class="-m-2 min-h-11 min-w-11 p-2 text-slate-400 hover:text-slate-600">
                        <span class="sr-only">Закрыть</span>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                @if($this->items->isEmpty())
                    <div class="flex flex-1 flex-col items-center justify-center px-6 text-center">
                        <p class="text-base font-medium text-slate-800">Корзина пуста</p>
                        <p class="mt-2 text-sm text-slate-500">Добавьте товары из каталога, и они появятся здесь.</p>
                        <button type="button" wire:click="closeDrawer" class="btn-store-cta-sm mt-6">
                            Продолжить покупки
                        </button>
                    </div>
                @else
                    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
                        <div class="space-y-4">
                            @foreach($this->items as $item)
                                <div class="flex gap-3 rounded-xl border border-orange-100/90 bg-stone-50/50 p-3" wire:key="drawer-item-{{ $item->id }}">
                                    <a href="{{ route('product.show', $item->product) }}" class="h-20 w-20 shrink-0 overflow-hidden rounded bg-slate-100">
                                        @if($item->product?->mainImage?->storage_url)
                                            <img
                                                src="{{ $item->product->mainImage->storage_url }}"
                                                alt="{{ $item->product->mainImage->alt ?? $item->product->name }}"
                                                class="h-full w-full object-cover"
                                            >
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-xs text-slate-400">Нет фото</div>
                                        @endif
                                    </a>

                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('product.show', $item->product) }}" class="line-clamp-2 text-sm font-medium text-slate-900 hover:text-slate-700">
                                            {{ $item->product->name }}
                                        </a>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item->product->sku }}</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ number_format((float) $item->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</p>

                                        <div class="mt-3 flex items-center justify-between gap-3">
                                            <div class="inline-flex items-center rounded-lg border border-slate-200">
                                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="px-3 py-1.5 text-slate-600 hover:bg-slate-50">−</button>
                                                <span class="min-w-10 px-2 text-center text-sm">{{ $item->quantity }}</span>
                                                <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="px-3 py-1.5 text-slate-600 hover:bg-slate-50">+</button>
                                            </div>

                                            <button type="button" wire:click="removeItem({{ $item->id }})" class="text-xs font-medium text-rose-600 hover:text-rose-700">
                                                Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="border-t border-slate-200 px-4 py-4 sm:px-5">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="text-sm text-slate-500">Итого</span>
                            <span class="text-lg font-semibold text-slate-900">{{ number_format($this->subtotal, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                        </div>

                        <div class="grid gap-3">
                            <a href="{{ route('cart') }}" class="inline-flex justify-center rounded-xl border border-orange-200 bg-white px-4 py-3 text-sm font-medium text-stone-700 transition hover:border-orange-300 hover:bg-orange-50/50">
                                Перейти в корзину
                            </a>
                            <a href="{{ route('checkout') }}" class="btn-store-cta-sm w-full justify-center py-3">
                                Оформить заказ
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
