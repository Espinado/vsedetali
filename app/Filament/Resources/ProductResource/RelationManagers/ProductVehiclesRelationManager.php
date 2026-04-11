<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Support\FilamentSweetAlert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductVehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'productVehicles';

    protected static ?string $title = 'Совместимость с автомобилями';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('vehicle_id')
                    ->label('Автомобиль')
                    ->relationship('vehicle', 'model')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->make . ' ' . $record->model . ' ' . ($record->generation ?? '')))
                    ->searchable(['make', 'model', 'generation'])
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
            ->recordTitleAttribute('vehicle.model')
            ->columns([
                Tables\Columns\TextColumn::make('vehicle.make')
                    ->label('Марка')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.model')
                    ->label('Модель')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('vehicle.generation')
                    ->label('Поколение')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('years')
                    ->label('Годы')
                    ->state(fn ($record): string => trim(($record->vehicle->year_from ?? '—') . ' - ' . ($record->vehicle->year_to ?? '—'))),
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
                tap(Tables\Actions\DeleteAction::make(), function (Tables\Actions\DeleteAction $action): void {
                    FilamentSweetAlert::configureTableDelete($action, 'Удалить привязку к автомобилю?', null);
                }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    tap(Tables\Actions\DeleteBulkAction::make(), function (Tables\Actions\DeleteBulkAction $action): void {
                        FilamentSweetAlert::configureBulkDelete($action, 'Удалить выбранные привязки?', 'Будет удалено записей:');
                    }),
                ]),
            ]);
    }
}
