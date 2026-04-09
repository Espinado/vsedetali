<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWarehouseResource;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use AuthorizesWarehouseResource;

    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Склады';

    protected static ?string $navigationGroup = 'Склад';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('seller');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->description('Склад без продавца — ваш основной склад (площадка). Склад с указанным продавцом — точка отгрузки этого продавца на вашей площадке. В карточке товара остатки задаются по каждому складу отдельно.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Код')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('seller_id')
                            ->label('Продавец')
                            ->relationship('seller', 'name', fn (Builder $query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('— Площадка (наш основной склад)')
                            ->helperText('Не выбирайте продавца — это ваш склад. Выберите продавца — склад хранения/отгрузки этого продавца на маркетплейсе.'),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Склад по умолчанию')
                            ->helperText('Обычно один «главный» склад площадки; для продавцов — по договорённости.')
                            ->default(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
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
                Tables\Columns\TextColumn::make('owner_label')
                    ->label('Владелец')
                    ->badge()
                    ->color(fn (Warehouse $record): string => $record->isPlatformWarehouse() ? 'success' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('seller_id', $direction))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->whereNull('seller_id')
                                ->orWhereHas('seller', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"));
                        });
                    }),
                Tables\Columns\TextColumn::make('stocks_count')
                    ->label('Остатков')
                    ->counts('stocks'),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('По умолчанию')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('ownership')
                    ->label('Владелец')
                    ->options([
                        'platform' => 'Только площадка (мы)',
                        'sellers' => 'Только продавцы',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;
                        if ($v === 'platform') {
                            return $query->whereNull('seller_id');
                        }
                        if ($v === 'sellers') {
                            return $query->whereNotNull('seller_id');
                        }

                        return $query;
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Активен'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Склад по умолчанию'),
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
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
