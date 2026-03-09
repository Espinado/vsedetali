<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Оформление заказа</h1>

    {{-- Step indicator --}}
    <nav class="flex items-center gap-2 mb-8 text-sm">
        <span class="{{ $step >= 1 ? 'text-indigo-600 font-medium' : 'text-slate-400' }}">1. Контакты</span>
        <span class="text-slate-300">→</span>
        <span class="{{ $step >= 2 ? 'text-indigo-600 font-medium' : 'text-slate-400' }}">2. Доставка</span>
        <span class="text-slate-300">→</span>
        <span class="{{ $step >= 3 ? 'text-indigo-600 font-medium' : 'text-slate-400' }}">3. Подтверждение</span>
    </nav>

    @if ($this->cart->cartItems->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-800">
            <p>Корзина пуста. <a href="{{ route('catalog') }}" class="underline font-medium">Перейти в каталог</a></p>
        </div>
    @else

    <form wire:submit.prevent="{{ $step === 1 ? 'step1Next' : ($step === 2 ? 'step2Next' : 'placeOrder') }}" class="space-y-6">
        @error('order')
            <div class="p-3 bg-red-100 text-red-800 rounded">{{ $message }}</div>
        @enderror

        {{-- Step 1: Contacts --}}
        @if ($step === 1)
            <div class="space-y-4 bg-white rounded-lg border border-slate-200 p-6">
                <h2 class="text-lg font-semibold">Контактные данные</h2>
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-slate-700 mb-1">Имя *</label>
                    <input type="text" id="customer_name" wire:model="customer_name"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('customer_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="customer_email" class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                    <input type="email" id="customer_email" wire:model="customer_email"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('customer_email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
                    <input type="text" id="customer_phone" wire:model="customer_phone"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('customer_phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Далее →
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 2: Delivery --}}
        @if ($step === 2)
            <div class="space-y-6">
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Адрес доставки</h2>
                    @if ($this->addresses->isNotEmpty())
                        <div class="space-y-2 mb-4">
                            @foreach ($this->addresses as $addr)
                                <label class="flex items-start gap-3 p-3 border rounded cursor-pointer hover:bg-slate-50">
                                    <input type="radio" wire:model="address_id" value="{{ $addr->id }}" class="mt-1">
                                    <span>
                                        <span class="font-medium">{{ $addr->name ?: 'Адрес' }}</span><br>
                                        <span class="text-slate-600 text-sm">{{ $addr->full_address }}, {{ $addr->city }}{{ $addr->postcode ? ', ' . $addr->postcode : '' }}</span>
                                    </span>
                                </label>
                            @endforeach
                            <label class="flex items-start gap-3 p-3 border rounded cursor-pointer hover:bg-slate-50">
                                <input type="radio" wire:model="address_id" value="0" class="mt-1">
                                <span>Новый адрес</span>
                            </label>
                        </div>
                    @endif
                    @if ($this->addresses->isEmpty() || !$address_id)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Получатель *</label>
                                <input type="text" wire:model="delivery_name" class="w-full rounded border-slate-300">
                                @error('delivery_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Адрес *</label>
                                <input type="text" wire:model="delivery_full_address" class="w-full rounded border-slate-300">
                                @error('delivery_full_address') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Город *</label>
                                <input type="text" wire:model="delivery_city" class="w-full rounded border-slate-300">
                                @error('delivery_city') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Регион</label>
                                <input type="text" wire:model="delivery_region" class="w-full rounded border-slate-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Индекс</label>
                                <input type="text" wire:model="delivery_postcode" class="w-full rounded border-slate-300">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
                                <input type="text" wire:model="delivery_phone" class="w-full rounded border-slate-300">
                            </div>
                        </div>
                    @else
                        <p class="text-slate-600 text-sm">Доставка по выбранному адресу.</p>
                    @endif
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Способ доставки *</h2>
                    <div class="space-y-2">
                        @foreach ($this->shippingMethods as $method)
                            <label class="flex items-center gap-3 p-3 border rounded cursor-pointer hover:bg-slate-50">
                                <input type="radio" wire:model="shipping_method_id" value="{{ $method->id }}" class="rounded-full">
                                <span class="flex-1">{{ $method->name }}</span>
                                <span class="font-medium">
                                    @if ($method->free_from && $this->subtotal >= (float) $method->free_from)
                                        Бесплатно
                                    @else
                                        {{ number_format((float) $method->cost, 2) }} €
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('shipping_method_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Способ оплаты *</h2>
                    <div class="space-y-2">
                        @foreach ($this->paymentMethods as $method)
                            <label class="flex items-center gap-3 p-3 border rounded cursor-pointer hover:bg-slate-50">
                                <input type="radio" wire:model="payment_method_id" value="{{ $method->id }}" class="rounded-full">
                                <span>{{ $method->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="flex gap-3">
                    <button type="button" wire:click="stepBack" class="px-4 py-2 border border-slate-300 rounded hover:bg-slate-50">← Назад</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Далее →</button>
                </div>
            </div>
        @endif

        {{-- Step 3: Confirm --}}
        @if ($step === 3)
            <div class="space-y-6">
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Итого</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt>Товары</dt>
                            <dd>{{ number_format($this->subtotal, 2) }} €</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Доставка</dt>
                            <dd>{{ number_format($this->shippingCost, 2) }} €</dd>
                        </div>
                        <div class="flex justify-between text-base font-semibold pt-2 border-t">
                            <dt>К оплате</dt>
                            <dd>{{ number_format($this->total, 2) }} €</dd>
                        </div>
                    </dl>
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Состав заказа</h2>
                    <ul class="divide-y divide-slate-200">
                        @foreach ($this->cart->cartItems as $item)
                            <li class="py-2 flex justify-between text-sm">
                                <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                                <span>{{ number_format($item->price * $item->quantity, 2) }} €</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Комментарий к заказу</label>
                    <textarea wire:model="comment" rows="2" class="w-full rounded border-slate-300" placeholder="Необязательно"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" wire:click="stepBack" class="px-4 py-2 border border-slate-300 rounded hover:bg-slate-50">← Назад</button>
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded font-medium hover:bg-green-700" wire:loading.attr="disabled">
                        <span wire:loading.remove>Оформить заказ</span>
                        <span wire:loading>Оформление…</span>
                    </button>
                </div>
            </div>
        @endif
    </form>
    @endif
</div>
