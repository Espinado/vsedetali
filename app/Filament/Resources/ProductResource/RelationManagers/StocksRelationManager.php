<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Наличие по складам';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->description('Остаток, резерв и срок на складе — по каждому активному складу отдельная строка. Склад без продавца — наш; с продавцом — склад этого продавца на площадке. Справочник: «Склады».')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Склад')
                            ->relationship(
                                'warehouse',
                                'name',
                                fn ($query) => $query->where('is_active', true)->with('seller')->orderByRaw('seller_id IS NULL DESC')->orderBy('name')
                            )
                            ->getOptionLabelFromRecordUsing(function (Warehouse $record): string {
                                if ($record->isPlatformWarehouse()) {
                                    return $record->name.' — площадка';
                                }

                                return $record->name.' — '.($record->seller?->name ?? 'продавец');
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Для пары «товар + склад» допустима только одна запись.'),
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
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['warehouse.seller']))
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')->label('Склад')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('warehouse.seller_id')
                    ->label('Владелец')
                    ->formatStateUsing(function ($state, $record): string {
                        $w = $record->warehouse;
                        if (! $w) {
                            return '—';
                        }

                        return $w->isPlatformWarehouse()
                            ? 'Площадка'
                            : (string) ($w->seller?->name ?? 'Продавец');
                    })
                    ->badge()
                    ->toggleable(),
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
