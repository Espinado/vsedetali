<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Support\FilamentSweetAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';

    protected static ?string $title = 'Характеристики';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('value')
                    ->label('Значение')
                    ->required()
                    ->maxLength(500),
                Forms\Components\TextInput::make('sort')
                    ->label('Сортировка')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Значение')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('sort')
                    ->label('Сортировка')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                tap(Tables\Actions\DeleteAction::make(), function (Tables\Actions\DeleteAction $action): void {
                    FilamentSweetAlert::configureTableDelete($action, 'Удалить характеристику?', null);
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    tap(Tables\Actions\DeleteBulkAction::make(), function (Tables\Actions\DeleteBulkAction $action): void {
                        FilamentSweetAlert::configureBulkDelete($action, 'Удалить выбранные характеристики?', 'Будет удалено записей:');
                    }),
                ]),
            ]);
    }
}
