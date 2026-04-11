<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderStatus;
use App\Models\Shipment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Отгрузки';

    protected function shipmentStatuses(): array
    {
        return [
            'pending' => 'Ожидает сборки',
            'packed' => 'Собран',
            'shipped' => 'Отгружен',
            'delivered' => 'Доставлен',
            'cancelled' => 'Отменён',
        ];
    }

    protected function syncOrderStatus(Shipment $shipment): void
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('shipping_method_id')
                    ->label('Способ доставки')
                    ->relationship('shippingMethod', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->default(fn (): ?int => $this->getOwnerRecord()->shipping_method_id),
                Forms\Components\Select::make('status')
                    ->label('Статус отгрузки')
                    ->options($this->shipmentStatuses())
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tracking_number')
            ->columns([
                Tables\Columns\TextColumn::make('shippingMethod.name')
                    ->label('Доставка')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->shipmentStatuses()[$state] ?? $state)
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
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
