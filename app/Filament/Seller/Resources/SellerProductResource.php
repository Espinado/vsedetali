<?php

namespace App\Filament\Seller\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Seller\Resources\SellerProductResource\Pages;
use App\Models\Product;
use App\Models\SellerProduct;
use App\Models\SellerStaff;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellerProductResource extends Resource
{
    protected static ?string $model = SellerProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Товары на площадке';

    protected static ?string $modelLabel = 'Позиция';

    protected static ?string $pluralModelLabel = 'Товары на площадке';

    protected static ?int $navigationSort = 10;

    protected static function staff(): ?SellerStaff
    {
        $u = auth('seller_staff')->user();

        return $u instanceof SellerStaff ? $u : null;
    }

    public static function canViewAny(): bool
    {
        $s = static::staff();

        return $s !== null && $s->can(StaffPermission::CATALOG_MANAGE);
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();
        $s = static::staff();
        if (! $s) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where('seller_id', $s->seller_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->description('Новая позиция отправляется на модерацию площадки. После одобрения статус станет «Активна».')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Товар из каталога')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return Product::query()
                                    ->where(function (Builder $q) use ($search): void {
                                        $q->where('name', 'like', '%'.$search.'%')
                                            ->orWhere('sku', 'like', '%'.$search.'%')
                                            ->orWhere('code', 'like', '%'.$search.'%');
                                    })
                                    ->orderBy('name')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn (Product $p): array => [$p->id => $p->name.' ('.$p->sku.')'])
                                    ->all();
                            })
                            ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name)
                            ->required()
                            ->disabledOn('edit'),
                        Forms\Components\TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Количество')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('Склад отгрузки')
                            ->options(function (): array {
                                $s = static::staff();
                                if (! $s) {
                                    return [];
                                }

                                return Warehouse::query()
                                    ->where('seller_id', $s->seller_id)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->nullable()
                            ->helperText('Склады создаёт администрация площадки в карточке склада.'),
                        Forms\Components\TextInput::make('status')
                            ->label('Статус')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'pending' => 'На модерации',
                                'active' => 'Активна',
                                'draft' => 'Черновик',
                                'paused' => 'Пауза',
                                'rejected' => 'Отклонена',
                                default => (string) $state,
                            })
                            ->visibleOn('edit'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.sku')
                    ->label('SKU')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Кол-во')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'На модерации',
                        'active' => 'Активна',
                        'draft' => 'Черновик',
                        'paused' => 'Пауза',
                        'rejected' => 'Отклонена',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'rejected' => 'danger',
                        'paused' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('updated_at', 'desc')
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
            'index' => Pages\ListSellerProducts::route('/'),
            'create' => Pages\CreateSellerProduct::route('/create'),
            'edit' => Pages\EditSellerProduct::route('/{record}/edit'),
        ];
    }
}
