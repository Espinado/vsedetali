<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SellerResource;
use App\Models\Order;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListMarketplaceOrders extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = SellerResource::class;

    protected static ?string $title = 'Заказы продавцов';

    protected static ?string $navigationLabel = null;

    protected static string $view = 'filament.resources.seller-resource.pages.list-marketplace-orders';

    public function mount(): void
    {
        static::authorizeResourceAccess();
        $this->mountInteractsWithTable();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return SellerResource::canViewAny();
    }

    /**
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        return SellerResource::getSellerHubSubNavigation();
    }

    protected function getTableQuery(): Builder
    {
        return Order::query()
            ->withMarketplaceSellerItems()
            ->with(['latestPayment', 'latestShipment.shippingMethod', 'paymentMethod', 'status', 'orderItems.seller']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('№')->sortable(),
                Tables\Columns\TextColumn::make('marketplace_seller_names')
                    ->label('Продавцы')
                    ->state(function (Order $record): string {
                        $names = $record->orderItems
                            ->whereNotNull('seller_id')
                            ->pluck('seller.name')
                            ->filter()
                            ->unique()
                            ->values();

                        return $names->isEmpty() ? '—' : $names->implode(', ');
                    }),
                Tables\Columns\TextColumn::make('customer_name')->label('Клиент')->searchable(),
                Tables\Columns\TextColumn::make('customer_email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('status.name')->label('Статус')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_status_label')
                    ->label('Оплата')
                    ->badge()
                    ->color(fn (Order $record): string => match ($record->latestPayment?->status) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('shipment_status_label')
                    ->label('Отгрузка')
                    ->badge()
                    ->color(fn (Order $record): string => match ($record->latestShipment?->status) {
                        'pending' => 'warning',
                        'packed' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')->label('Итого')->money('RUB')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Создан')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (Order $record): bool => OrderResource::canView($record)),
            ])
            ->bulkActions([]);
    }
}
