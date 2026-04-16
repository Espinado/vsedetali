<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section heading="Моя статистика" class="mt-6">
        @if ($row === null)
            <p class="text-sm text-gray-500">Нет данных для отображения.</p>
        @else
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="grid gap-4 md:grid-cols-4">
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
                </div>

                <div class="mt-4">
                    <svg viewBox="0 0 320 56" class="h-20 w-full">
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
        @endif
    </x-filament::section>
</x-filament-panels::page>
