<?php

namespace App\Filament\Pages;

use App\Authorization\StaffPermission;
use App\Models\Order;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceSettlementsPage extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Взаиморасчеты';

    protected static ?string $title = 'Взаиморасчеты';

    protected static ?string $slug = 'finance/settlements';

    protected static ?string $navigationGroup = 'Финансы';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.finance-settlements-page';

    public static function canAccess(): bool
    {
        $staff = auth('staff')->user();

        return $staff !== null && $staff->can(StaffPermission::FINANCE_VIEW);
    }

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->whereNotNull('invoice_number')
            ->with('latestPayment');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Номер инвойса')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Сумма')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status_label')
                    ->label('Статус оплаты')
                    ->badge()
                    ->color(fn (Order $record): string => match ($record->latestPayment?->status) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }
}
