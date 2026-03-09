<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Продажи';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status_id')
                    ->relationship('status', 'name')
                    ->required()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Клиент')->searchable(),
                Tables\Columns\TextColumn::make('customer_email')->searchable(),
                Tables\Columns\TextColumn::make('status.name')->label('Статус')->badge()->sortable(),
                Tables\Columns\TextColumn::make('total')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
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
                Infolists\Components\Section::make('Товары')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('orderItems')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name'),
                                Infolists\Components\TextEntry::make('sku'),
                                Infolists\Components\TextEntry::make('quantity'),
                                Infolists\Components\TextEntry::make('price')->money('EUR'),
                                Infolists\Components\TextEntry::make('total')->money('EUR'),
                            ])
                            ->columns(5),
                    ]),
                Infolists\Components\Section::make('Суммы')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')->label('Товары')->money('EUR'),
                        Infolists\Components\TextEntry::make('shipping_cost')->label('Доставка')->money('EUR'),
                        Infolists\Components\TextEntry::make('total')->label('Итого')->money('EUR')->weight('bold'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Доставка и оплата')
                    ->schema([
                        Infolists\Components\TextEntry::make('shippingMethod.name')->label('Способ доставки'),
                        Infolists\Components\TextEntry::make('paymentMethod.name')->label('Способ оплаты'),
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
                                $lines = array_filter([$addr->name, $addr->full_address, $addr->city . ($addr->postcode ? ' ' . $addr->postcode : ''), $addr->phone]);
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
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
