<?php

namespace App\Filament\Seller\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Resources\OrderResource as AdminOrderResource;
use App\Filament\Seller\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\SellerStaff;
use App\Models\Setting;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Заказы';

    protected static ?string $modelLabel = 'Заказ';

    protected static ?string $pluralModelLabel = 'Заказы';

    protected static ?int $navigationSort = 0;

    protected static function staff(): ?SellerStaff
    {
        $u = auth('seller_staff')->user();

        return $u instanceof SellerStaff ? $u : null;
    }

    public static function canViewAny(): bool
    {
        $s = static::staff();

        return $s !== null && ($s->can(StaffPermission::ORDERS_VIEW) || $s->can(StaffPermission::ORDERS_EDIT));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $s = static::staff();
        if (! $s) {
            return $q->whereRaw('1 = 0');
        }

        return $q
            ->withSellerItems($s->seller_id)
            ->with(['latestPayment', 'latestShipment.shippingMethod', 'paymentMethod', 'status', 'orderItems']);
    }

    public static function table(Table $table): Table
    {
        return AdminOrderResource::table($table)
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $sellerId = static::staff()?->seller_id;

        return $infolist
            ->schema([
                Infolists\Components\Section::make('Заказ')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('№'),
                        Infolists\Components\TextEntry::make('status.name')->label('Статус')->badge(),
                        Infolists\Components\TextEntry::make('created_at')->label('Дата')->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Клиент')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer_name')->label('Имя'),
                        Infolists\Components\TextEntry::make('customer_email')->label('Email'),
                        Infolists\Components\TextEntry::make('customer_phone')->label('Телефон'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Ваши позиции в заказе')
                    ->description('Остальные позиции могут быть у других продавцов или площадки; статус заказа общий.')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('orderItems')
                            ->label('')
                            ->state(fn (Order $record) => $record->orderItems->where('seller_id', $sellerId))
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name'),
                                Infolists\Components\TextEntry::make('sku'),
                                Infolists\Components\TextEntry::make('quantity'),
                                Infolists\Components\TextEntry::make('price')
                                    ->money(Setting::get('currency', 'RUB')),
                                Infolists\Components\TextEntry::make('total')
                                    ->money(Setting::get('currency', 'RUB')),
                            ])
                            ->columns(5),
                    ]),
                Infolists\Components\Section::make('Суммы')
                    ->schema([
                        Infolists\Components\TextEntry::make('seller_lines_total')
                            ->label('Сумма по вашим позициям')
                            ->state(function (Order $record) use ($sellerId): string {
                                $sum = $record->orderItems->where('seller_id', $sellerId)->sum('total');

                                return number_format((float) $sum, 2, '.', ' ').' '.Setting::get('currency', 'RUB');
                            }),
                        Infolists\Components\TextEntry::make('subtotal')->label('Товары (весь заказ)')->money(Setting::get('currency', 'RUB')),
                        Infolists\Components\TextEntry::make('shipping_cost')->label('Доставка')->money(Setting::get('currency', 'RUB')),
                        Infolists\Components\TextEntry::make('total')->label('Итого по заказу')->money(Setting::get('currency', 'RUB'))->weight('bold'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Доставка и оплата')
                    ->schema([
                        Infolists\Components\TextEntry::make('shippingMethod.name')->label('Способ доставки'),
                        Infolists\Components\TextEntry::make('paymentMethod.name')->label('Способ оплаты'),
                        Infolists\Components\TextEntry::make('payment_status_label')->label('Статус оплаты')->badge(),
                        Infolists\Components\TextEntry::make('latestPayment.paid_at')->label('Оплачено')->dateTime()->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Отгрузка')
                    ->schema([
                        Infolists\Components\TextEntry::make('shipment_status_label')->label('Статус отгрузки')->badge(),
                        Infolists\Components\TextEntry::make('latestShipment.shippingMethod.name')->label('Служба доставки')->placeholder('—'),
                        Infolists\Components\TextEntry::make('latestShipment.tracking_number')->label('Трек-номер')->placeholder('—'),
                        Infolists\Components\TextEntry::make('latestShipment.shipped_at')->label('Дата отгрузки')->dateTime()->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Адрес доставки')
                    ->schema([
                        Infolists\Components\TextEntry::make('shipping_address')
                            ->label('')
                            ->state(function (Order $record): string {
                                $addr = $record->shippingAddress();
                                if (! $addr) {
                                    return '—';
                                }
                                $lines = array_filter([$addr->name, $addr->full_address, $addr->city.($addr->postcode ? ' '.$addr->postcode : ''), $addr->phone]);

                                return implode("\n", $lines);
                            })
                            ->formatStateUsing(fn (?string $state): string => $state ? nl2br(e($state)) : '—')
                            ->html(),
                    ])
                    ->visible(fn (Order $record): bool => $record->shippingAddress() !== null),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
