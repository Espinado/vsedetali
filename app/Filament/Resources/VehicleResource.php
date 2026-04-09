<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesCatalogResource;
use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    use AuthorizesCatalogResource;

    protected static ?string $model = Vehicle::class;

    protected static ?string $recordTitleAttribute = 'model';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Каталог';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('make')
                    ->label('Марка')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('model')
                    ->label('Модель')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('generation')
                    ->label('Поколение')
                    ->maxLength(100)
                    ->nullable(),
                Forms\Components\TextInput::make('year_from')
                    ->label('Год от')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100)
                    ->nullable(),
                Forms\Components\TextInput::make('year_to')
                    ->label('Год до')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(2100)
                    ->nullable(),
                Forms\Components\TextInput::make('engine')
                    ->label('Двигатель')
                    ->maxLength(100)
                    ->nullable(),
                Forms\Components\TextInput::make('body_type')
                    ->label('Кузов')
                    ->maxLength(50)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('make')
                    ->label('Марка')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->label('Модель')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('generation')
                    ->label('Поколение')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('year_from')
                    ->label('Год от')
                    ->sortable(),
                Tables\Columns\TextColumn::make('year_to')
                    ->label('Год до')
                    ->sortable(),
                Tables\Columns\TextColumn::make('body_type')
                    ->label('Кузов')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('product_vehicles_count')
                    ->label('Совместимостей')
                    ->counts('productVehicles'),
            ])
            ->defaultSort('make')
            ->filters([
                Tables\Filters\SelectFilter::make('make')
                    ->label('Марка')
                    ->options(fn (): array => Vehicle::query()->orderBy('make')->distinct()->pluck('make', 'make')->all()),
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

    public static function getRelations(): array
    {
        return [
            VehicleResource\RelationManagers\ProductVehiclesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }
}
