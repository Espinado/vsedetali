<?php

namespace App\Filament\Widgets;

use App\Authorization\StaffPermission;
use App\Models\Order;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $staff = auth('staff')->user();

        return $staff !== null && $staff->can(StaffPermission::FINANCE_VIEW);
    }

    protected function getStats(): array
    {
        $currency = Setting::get('currency', 'RUB');
        $revenueMonth = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
        $revenueYear = Order::whereYear('created_at', now()->year)->sum('total');

        return [
            Stat::make('Выручка за месяц', number_format((float) $revenueMonth, 2, ',', ' ').' '.$currency),
            Stat::make('Оборот за год', number_format((float) $revenueYear, 2, ',', ' ').' '.$currency),
            Stat::make('Заказов всего', number_format(Order::count(), 0, ',', ' ')),
        ];
    }
}
