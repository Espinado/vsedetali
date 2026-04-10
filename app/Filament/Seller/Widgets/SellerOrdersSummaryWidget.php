<?php

namespace App\Filament\Seller\Widgets;

use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\SellerStaff;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SellerOrdersSummaryWidget extends BaseWidget
{
    protected static ?int $sort = -5;

    protected ?string $heading = 'Заказы и оборот';

    public static function canView(): bool
    {
        return auth('seller_staff')->check();
    }

    protected function getStats(): array
    {
        $staff = auth('seller_staff')->user();
        if (! $staff instanceof SellerStaff) {
            return [];
        }

        $sellerId = $staff->seller_id;
        $cancelledIds = OrderStatus::query()->where('slug', 'cancelled')->pluck('id');

        $currency = Setting::get('currency', '₽');
        $since30 = now()->subDays(30);
        $monthStart = now()->startOfMonth();

        $orders30 = $this->distinctOrderCount($sellerId, $cancelledIds, $since30);
        $turnover30 = $this->turnoverSum($sellerId, $cancelledIds, $since30);

        $ordersMonth = $this->distinctOrderCount($sellerId, $cancelledIds, $monthStart);
        $turnoverMonth = $this->turnoverSum($sellerId, $cancelledIds, $monthStart);

        $ordersAll = $this->distinctOrderCount($sellerId, $cancelledIds, null);
        $turnoverAll = $this->turnoverSum($sellerId, $cancelledIds, null);

        return [
            Stat::make('Заказов за 30 дней', number_format($orders30, 0, ',', ' '))
                ->description('Оборот: '.number_format((float) $turnover30, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-shopping-bag'),
            Stat::make('Заказов в этом месяце', number_format($ordersMonth, 0, ',', ' '))
                ->description('Оборот: '.number_format((float) $turnoverMonth, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-calendar-days'),
            Stat::make('Заказов всего', number_format($ordersAll, 0, ',', ' '))
                ->description('Оборот: '.number_format((float) $turnoverAll, 2, ',', ' ').' '.$currency)
                ->descriptionIcon('heroicon-m-banknotes'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $cancelledIds
     */
    private function distinctOrderCount(int $sellerId, $cancelledIds, ?Carbon $from): int
    {
        $q = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', function ($oq) use ($cancelledIds, $from): void {
                if ($from !== null) {
                    $oq->where('created_at', '>=', $from);
                }
                if ($cancelledIds->isNotEmpty()) {
                    $oq->whereNotIn('status_id', $cancelledIds);
                }
            });

        return $q->distinct()->pluck('order_id')->unique()->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $cancelledIds
     */
    private function turnoverSum(int $sellerId, $cancelledIds, ?Carbon $from): float
    {
        $q = OrderItem::query()
            ->where('seller_id', $sellerId)
            ->whereHas('order', function ($oq) use ($cancelledIds, $from): void {
                if ($from !== null) {
                    $oq->where('created_at', '>=', $from);
                }
                if ($cancelledIds->isNotEmpty()) {
                    $oq->whereNotIn('status_id', $cancelledIds);
                }
            });

        return (float) $q->sum('total');
    }
}
