<?php

namespace App\Filament\Seller\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Forms\VehicleCompatibilityRepeater;
use App\Filament\Seller\Resources\SellerProductResource\Pages;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\SellerProduct;
use App\Models\SellerStaff;
use App\Support\PublicStorageUrl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

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

        return $q->where('seller_id', $s->seller_id)->with(['warehouse', 'product.images', 'product.vehicles']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->description('Обязательны название, фото, цены, остаток, OEM, артикул, срок отгрузки; в совместимости — марка, модель и хотя бы годы или отмеченные записи справочника (можно оба). При создании позиция уходит на модерацию; при редактировании обновляются карточка товара в каталоге и поля площадки. Ошибки проверки — под полями и вверху формы.')
                    ->schema([
                        VehicleCompatibilityRepeater::make(),
                        Forms\Components\TextInput::make('listing_name')
                            ->label('Название')
                            ->maxLength(500)
                            ->required(),
                        Forms\Components\FileUpload::make('listing_images')
                            ->label('Фотографии')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(12)
                            ->disk('public')
                            ->directory('seller-listings')
                            ->visibility('public')
                            ->required()
                            ->helperText('Можно одно или несколько фото; перетащите для порядка, первое — главное. Лишние можно удалить кнопкой у файла.'),
                        Forms\Components\TextInput::make('price')
                            ->label('Цена')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01),
                        Forms\Components\TextInput::make('cost_price')
                            ->label('Себестоимость')
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
                        Forms\Components\TextInput::make('oem_code')
                            ->label('OEM-код')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('article')
                            ->label('Артикул')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('shipping_days')
                            ->label('Срок отгрузки, дней')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(0)
                            ->maxValue(999)
                            ->helperText('Рабочих дней до отгрузки после заказа (по договорённости с площадкой).'),
                        Forms\Components\Placeholder::make('warehouse_auto')
                            ->label('Склад отгрузки')
                            ->content(function (?Model $record): string {
                                if ($record instanceof SellerProduct) {
                                    return $record->warehouse?->name ?? '—';
                                }

                                return 'Назначается автоматически — ваш активный склад на площадке (единственный или первый по списку).';
                            }),
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
                Tables\Columns\TextColumn::make('product_id')
                    ->label('')
                    ->searchable(false)
                    ->sortable(false)
                    ->getStateUsing(function (Model $record): string {
                        if (! $record instanceof SellerProduct) {
                            return '';
                        }
                        $img = $record->product?->images->firstWhere('is_main', true)
                            ?? $record->product?->images->first();
                        $path = $img?->path;
                        if ($path === null || $path === '') {
                            return '';
                        }

                        return (string) (PublicStorageUrl::from($path) ?? '');
                    })
                    ->formatStateUsing(function ($state): HtmlString {
                        $url = is_string($state) ? $state : '';

                        if ($url === '') {
                            return new HtmlString('<span class="text-sm text-gray-400">—</span>');
                        }

                        return new HtmlString(
                            '<img src="'.e($url).'" alt="" class="h-12 w-12 rounded-md object-cover" width="48" height="48" loading="lazy" />'
                        );
                    })
                    ->html(),
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
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Себест.')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Кол-во')
                    ->sortable(),
                Tables\Columns\TextColumn::make('oem_code')
                    ->label('OEM')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('article')
                    ->label('Артикул')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipping_days')
                    ->label('Отгрузка, дн.')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    tap(Tables\Actions\DeleteBulkAction::make(), function (Tables\Actions\DeleteBulkAction $action): void {
                        FilamentSweetAlert::configureBulkDelete($action, 'Удалить выбранные позиции?', 'Будет удалено позиций:');
                    }),
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
