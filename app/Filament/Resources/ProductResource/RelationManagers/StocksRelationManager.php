<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Остатки по складам';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Остаток на складе')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label('Резерв')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Forms\Components\TextInput::make('days_in_warehouse')
                    ->label('Дней на складе')
                    ->numeric()
                    ->nullable()
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')->label('Склад')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->sortable()->label('Остаток'),
                Tables\Columns\TextColumn::make('reserved_quantity')->sortable()->label('Резерв'),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Доступно')
                    ->state(fn ($record) => $record->available_quantity),
                Tables\Columns\TextColumn::make('days_in_warehouse')->label('Дней на складе')->sortable(),
            ])
            ->defaultSort('warehouse_id')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
