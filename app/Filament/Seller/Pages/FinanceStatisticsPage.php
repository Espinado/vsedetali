<?php

namespace App\Filament\Seller\Pages;

use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\SellerStaff;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class FinanceStatisticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Статистика';

    protected static ?string $title = 'Финансовая статистика';

    protected static ?string $slug = 'finance/statistics';

    protected static ?string $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.seller.pages.finance-statistics-page';

    public ?array $filters = [];

    /** @var array<string, mixed>|null */
    public ?array $row = null;

    public string $currency = 'RUB';

    public static function canAccess(): bool
    {
        return auth('seller_staff')->check();
    }

    public function mount(): void
    {
        $this->form->fill($this->defaultFilters());
        $this->loadStats();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Период')
                    ->schema([
                        Forms\Components\Select::make('preset')
                            ->label('Период')
                            ->options([
                                'current_month' => 'Текущий месяц',
                                'last_month' => 'Прошлый месяц',
                                'custom' => 'С даты по дату',
                            ])
                            ->default('current_month')
                            ->required()
                            ->live(),
                        Forms\Components\DatePicker::make('date_from')
                            ->label('С даты')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->live(),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('По дату')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->live(),
                    ])
                    ->columns(3),
            ])
            ->statePath('filters');
    }

    public function updatedFiltersPreset(?string $state): void
    {
        if ($state === 'current_month') {
            $this->filters['date_from'] = now()->startOfMonth()->toDateString();
            $this->filters['date_to'] = now()->endOfMonth()->toDateString();
        } elseif ($state === 'last_month') {
            $this->filters['date_from'] = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $this->filters['date_to'] = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        }

        $this->loadStats();
    }

    public function updatedFiltersDateFrom(): void
    {
        $this->filters['preset'] = 'custom';
        $this->loadStats();
    }

    public function updatedFiltersDateTo(): void
    {
        $this->filters['preset'] = 'custom';
        $this->loadStats();
    }

    /**
     * @return array<string, string>
     */
    private function defaultFilters(): array
    {
        return [
            'preset' => 'current_month',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ];
    }

    private function loadStats(): void
    {
        $staff = auth('seller_staff')->user();
        if (! $staff instanceof SellerStaff) {
            $this->row = null;

            return;
        }

        $dateFrom = Carbon::parse((string) ($this->filters['date_from'] ?? now()->startOfMonth()->toDateString()))->startOfDay();
        $dateTo = Carbon::parse((string) ($this->filters['date_to'] ?? now()->endOfMonth()->toDateString()))->endOfDay();

        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $this->currency = (string) Setting::get('currency', 'RUB');
        $cancelledIds = OrderStatus::query()->where('slug', 'cancelled')->pluck('id');

        $baseQuery = OrderItem::query()
            ->selectRaw('DATE(orders.created_at) as sale_date, SUM(order_items.total) as turnover')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('seller_id', $staff->seller_id)
            ->whereBetween('orders.created_at', [$dateFrom, $dateTo])
            ->groupBy('sale_date')
            ->orderBy('sale_date');

        if ($cancelledIds->isNotEmpty()) {
            $baseQuery->whereNotIn('orders.status_id', $cancelledIds);
        }

        $aggregated = $baseQuery->get()->keyBy('sale_date');

        $days = [];
        $cursor = $dateFrom->copy()->startOfDay();
        while ($cursor->lte($dateTo)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $dailyTotals = [];
        $turnover = 0.0;
        foreach ($days as $day) {
            $dayTotal = (float) ($aggregated[$day]->turnover ?? 0);
            $dailyTotals[] = $dayTotal;
            $turnover += $dayTotal;
        }

        $commissionPercent = (float) ($staff->seller?->commission_percent ?? 0);
        $this->row = [
            'seller_name' => (string) ($staff->seller?->name ?? 'Мой магазин'),
            'turnover' => $turnover,
            'commission_percent' => $commissionPercent,
            'commission_amount' => round($turnover * ($commissionPercent / 100), 2),
            'sparkline' => $this->sparklinePoints($dailyTotals),
        ];
    }

    /**
     * @param  array<int, float>  $dailyTotals
     */
    private function sparklinePoints(array $dailyTotals): string
    {
        if ($dailyTotals === []) {
            return '0,40 100,40';
        }

        $width = 320;
        $height = 56;
        $count = count($dailyTotals);
        $max = max($dailyTotals);
        $safeMax = $max > 0 ? $max : 1.0;

        $points = [];
        foreach ($dailyTotals as $index => $value) {
            $x = $count > 1 ? ($index / ($count - 1)) * $width : $width / 2;
            $y = $height - (($value / $safeMax) * $height);
            $points[] = round($x, 2).','.round($y, 2);
        }

        return implode(' ', $points);
    }
}
