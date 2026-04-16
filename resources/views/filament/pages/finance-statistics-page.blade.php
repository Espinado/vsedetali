<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section heading="Продажи продавцов" class="mt-6">
        @if ($rows === [])
            <p class="text-sm text-gray-500">За выбранный период продаж у продавцов нет.</p>
        @else
            <div class="space-y-4">
                @foreach ($rows as $row)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="grid gap-4 md:grid-cols-5">
                            <div>
                                <p class="text-xs text-gray-500">Продавец</p>
                                <p class="font-medium">{{ $row['seller_name'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Продажи</p>
                                <p class="font-medium">{{ number_format((float) $row['turnover'], 2, ',', ' ') }} {{ $currency }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Комиссия</p>
                                <p class="font-medium">{{ number_format((float) $row['commission_percent'], 2, ',', ' ') }}%</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">К оплате площадке</p>
                                <p class="font-medium">{{ number_format((float) $row['commission_amount'], 2, ',', ' ') }} {{ $currency }}</p>
                            </div>
                            <div class="flex items-center md:justify-end">
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="generateInvoice({{ $row['seller_id'] }})"
                                >
                                    Сгенерировать инвойс
                                </x-filament::button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <svg viewBox="0 0 220 52" class="h-16 w-full">
                                <polyline
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="text-primary-600"
                                    points="{{ $row['sparkline'] }}"
                                />
                            </svg>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
