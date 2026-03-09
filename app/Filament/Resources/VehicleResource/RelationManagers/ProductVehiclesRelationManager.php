<?php

namespace App\Filament\Resources\VehicleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductVehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'productVehicles';

    protected static ?string $title = 'Совместимые товары';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Товар')
                    ->relationship('product', 'name')
                    ->searchable(['name', 'sku'])
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('oem_number')
                    ->label('OEM номер')
                    ->maxLength(100)
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->sortable()
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('product.brand.name')
                    ->label('Бренд')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('oem_number')
                    ->label('OEM')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->defaultSort('id', 'desc')
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
