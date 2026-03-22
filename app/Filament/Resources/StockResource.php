<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockResource\Pages;
use App\Models\Stock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockResource extends Resource
{
    protected static ?string $model = Stock::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Склад';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('warehouse_id')
                    ->label('Склад')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Для каждого товара и склада должна быть одна запись остатка.'),
                Forms\Components\TextInput::make('quantity')
                    ->label('Остаток на складе')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label('Резерв')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('days_in_warehouse')
                    ->label('Дней на складе')
                    ->numeric()
                    ->nullable()
                    ->minValue(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.code')
                    ->label('Код')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('Артикул')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Наименование')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Остаток')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Резерв')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Доступно')
                    ->state(fn (Stock $record): int => $record->available_quantity)
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw('(quantity - reserved_quantity) ' . $direction)),
                Tables\Columns\TextColumn::make('product.cost_price')
                    ->label('Себестоимость')
                    ->money('EUR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cost_total')
                    ->label('Сумма себестоимости')
                    ->state(fn (Stock $record): string => number_format((float) $record->quantity * (float) ($record->product?->cost_price ?? 0), 2, '.', ' '))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product.price')
                    ->label('Продажная цена')
                    ->money('EUR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sale_total')
                    ->label('Сумма продажи')
                    ->state(fn (Stock $record): string => number_format((float) $record->quantity * (float) ($record->product?->price ?? 0), 2, '.', ' '))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('days_in_warehouse')
                    ->label('Дней на складе')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Склад')
                    ->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStocks::route('/'),
            'create' => Pages\CreateStock::route('/create'),
            'edit' => Pages\EditStock::route('/{record}/edit'),
        ];
    }
}
