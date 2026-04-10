<?php

namespace App\Filament\Resources\SellerResource\RelationManagers;

use App\Filament\Resources\SellerResource;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WarehousesRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouses';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Склады';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return SellerResource::canViewAny();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('seller_id')
                    ->default(fn (): int|string => $this->getOwnerRecord()->getKey())
                    ->dehydrated(),
                Forms\Components\Hidden::make('is_default')
                    ->default(false)
                    ->dehydrated(),
                Forms\Components\Section::make()
                    ->description('Точка отгрузки этого продавца на площадке. «Склад по умолчанию для площадки» задаётся только у складов без продавца (не здесь).')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Код')
                            ->required()
                            ->maxLength(50)
                            ->unique(Warehouse::class, 'code', ignoreRecord: true),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stocks_count')
                    ->label('Остатков')
                    ->counts('stocks'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
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
