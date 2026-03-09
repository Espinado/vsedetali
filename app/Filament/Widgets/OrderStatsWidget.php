<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalOrders = Order::count();
        $ordersToday = Order::whereDate('created_at', today())->count();
        $revenueMonth = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');

        return [
            Stat::make('Всего заказов', number_format($totalOrders, 0, ',', ' ')),
            Stat::make('Заказов сегодня', number_format($ordersToday, 0, ',', ' ')),
            Stat::make('Выручка за месяц', number_format($revenueMonth, 2, ',', ' ') . ' ' . \App\Models\Setting::get('currency', 'EUR')),
        ];
    }
}
