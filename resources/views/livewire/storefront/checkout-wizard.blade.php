<div class="mx-auto max-w-3xl px-0 sm:px-0">
    <h1 class="mb-4 text-xl font-bold sm:mb-6 sm:text-2xl">Оформление заказа</h1>

    {{-- Step indicator --}}
    <nav class="mb-6 flex flex-col gap-2 text-sm sm:mb-8 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-2 sm:gap-y-1" aria-label="Шаги оформления">
        <span class="{{ $step >= 1 ? 'text-orange-600 font-medium' : 'text-slate-400' }}">1. Контакты</span>
        <span class="hidden text-slate-300 sm:inline" aria-hidden="true">→</span>
        <span class="{{ $step >= 2 ? 'text-orange-600 font-medium' : 'text-slate-400' }}">2. Доставка</span>
        <span class="hidden text-slate-300 sm:inline" aria-hidden="true">→</span>
        <span class="{{ $step >= 3 ? 'text-orange-600 font-medium' : 'text-slate-400' }}">3. Подтверждение</span>
    </nav>

    @if ($this->cart->cartItems->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-800">
            <p>Корзина пуста. <a href="{{ route('home') }}" class="underline font-medium">На главную</a></p>
        </div>
    @else

    <form wire:submit.prevent="{{ $step === 1 ? 'step1Next' : ($step === 2 ? 'step2Next' : 'placeOrder') }}" class="space-y-6">
        @error('order')
            <div class="p-3 bg-red-100 text-red-800 rounded">{{ $message }}</div>
        @enderror

        {{-- Step 1: Contacts --}}
        @if ($step === 1)
            <div class="space-y-4 rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
                <h2 class="text-lg font-semibold">Контактные данные</h2>
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-slate-700 mb-1">Имя *</label>
                    <input type="text" id="customer_name" wire:model="customer_name"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    @error('customer_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="customer_email" class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                    <input type="email" id="customer_email" wire:model="customer_email"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    @error('customer_email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
                    <input type="text" id="customer_phone" wire:model="customer_phone"
                           class="w-full rounded border-slate-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                    @error('customer_phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="btn-store-cta w-full sm:w-auto">
                        Далее →
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 2: Delivery --}}
        @if ($step === 2)
            <div class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold">Адрес доставки</h2>
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
                {{-- Способ доставки и оплаты — планируются в будущем
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Способ доставки *</h2>
                    ...
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold mb-4">Способ оплаты *</h2>
                    ...
                </div>
                --}}
                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <button type="button" wire:click="stepBack" class="min-h-11 w-full rounded border border-slate-300 px-4 py-2.5 hover:bg-slate-50 sm:w-auto">← Назад</button>
                    <button type="submit" class="btn-store-cta w-full sm:w-auto">Далее →</button>
                </div>
            </div>
        @endif

        {{-- Step 3: Confirm --}}
        @if ($step === 3)
            <div class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold">Итого</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt>Товары</dt>
                            <dd>{{ number_format($this->subtotal, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Доставка</dt>
                            <dd>{{ number_format($this->shippingCost, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</dd>
                        </div>
                        <div class="flex justify-between text-base font-semibold pt-2 border-t">
                            <dt>К оплате</dt>
                            <dd>{{ number_format($this->total, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
                    <h2 class="mb-4 text-lg font-semibold">Состав заказа</h2>
                    <ul class="divide-y divide-slate-200">
                        @foreach ($this->cart->cartItems as $item)
                            <li class="flex flex-col gap-1 py-3 text-sm sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                                <span class="min-w-0 break-words">{{ $item->product->name }} × {{ $item->quantity }}</span>
                                <span class="shrink-0 font-medium sm:text-right">{{ number_format($item->price * $item->quantity, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Комментарий к заказу</label>
                    <textarea wire:model="comment" rows="2" class="w-full rounded border-slate-300" placeholder="Необязательно"></textarea>
                </div>
                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <button type="button" wire:click="stepBack" class="min-h-11 w-full rounded border border-slate-300 px-4 py-2.5 hover:bg-slate-50 sm:w-auto">← Назад</button>
                    <button type="submit" class="btn-store-cta w-full min-h-12 py-3.5 sm:w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove>Оформить заказ</span>
                        <span wire:loading>Оформление…</span>
                    </button>
                </div>
            </div>
        @endif
    </form>
    @endif
</div>
