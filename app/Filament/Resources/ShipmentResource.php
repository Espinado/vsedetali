<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\OrderStatus;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Продажи';

    protected static ?int $navigationSort = 20;

    protected static function shipmentStatuses(): array
    {
        return [
            'pending' => 'Ожидает сборки',
            'packed' => 'Собран',
            'shipped' => 'Отгружен',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменён',
        ];
    }

    public static function syncOrderStatus(Shipment $shipment): void
    {
        $slug = match ($shipment->status) {
            'pending', 'packed' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => null,
        };

        if (! $slug) {
            return;
        }

        $status = OrderStatus::where('slug', $slug)->first();

        if ($status) {
            $shipment->order()->update(['status_id' => $status->id]);
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('order_id')
                    ->label('Заказ')
                    ->relationship('order', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => '#' . $record->id . ' — ' . $record->customer_name)
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('shipping_method_id')
                    ->label('Способ доставки')
                    ->relationship('shippingMethod', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->label('Статус отгрузки')
                    ->options(static::shipmentStatuses())
                    ->required()
                    ->default('pending'),
                Forms\Components\TextInput::make('tracking_number')
                    ->label('Трек-номер')
                    ->maxLength(255)
                    ->nullable(),
                Forms\Components\DateTimePicker::make('shipped_at')
                    ->label('Дата отгрузки')
                    ->seconds(false)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Заказ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.customer_name')
                    ->label('Клиент')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shippingMethod.name')
                    ->label('Доставка')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::shipmentStatuses()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'packed' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Трек-номер')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('shipped_at')
                    ->label('Отгружено')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options(static::shipmentStatuses()),
                Tables\Filters\SelectFilter::make('shipping_method_id')
                    ->label('Способ доставки')
                    ->relationship('shippingMethod', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['order', 'shippingMethod']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }
}
