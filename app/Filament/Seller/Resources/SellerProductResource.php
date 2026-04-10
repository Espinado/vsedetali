<?php

namespace App\Filament\Seller\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Seller\Resources\SellerProductResource\Pages;
use App\Models\SellerProduct;
use App\Models\SellerStaff;
use App\Models\Vehicle;
use App\Support\PublicStorageUrl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
                    ->description('Все поля обязательны. При создании позиция уходит на модерацию; при редактировании обновляются карточка товара (название, фото, совместимость) и поля площадки. Совместимость — несколько строк «марка / модель / годы». Ошибки проверки показываются под полями и вверху формы.')
                    ->schema([
                        Forms\Components\Repeater::make('vehicle_compatibilities')
                            ->label('Совместимость с авто')
                            ->addActionLabel('Добавить марку и модель')
                            ->collapsible()
                            ->minItems(1)
                            ->maxItems(50)
                            ->defaultItems(1)
                            ->itemLabel(function (array $state): string {
                                $make = trim((string) ($state['vehicle_make'] ?? ''));
                                $model = trim((string) ($state['vehicle_model'] ?? ''));
                                $label = trim(implode(' ', array_filter([$make, $model])));

                                return $label !== '' ? $label : 'Марка, модель, годы';
                            })
                            ->schema([
                                Forms\Components\Select::make('vehicle_make')
                                    ->label('Марка')
                                    ->options(fn (): array => Vehicle::query()
                                        ->distinct()
                                        ->orderBy('make')
                                        ->pluck('make')
                                        ->mapWithKeys(fn (string $m): array => [$m => $m])
                                        ->all())
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('vehicle_model', null);
                                        $set('compatibility_years', null);
                                    })
                                    ->required(),
                                Forms\Components\Select::make('vehicle_model')
                                    ->label('Модель')
                                    ->options(function (Get $get): array {
                                        $make = $get('vehicle_make');
                                        if (blank($make)) {
                                            return [];
                                        }

                                        return Vehicle::query()
                                            ->where('make', $make)
                                            ->distinct()
                                            ->orderBy('model')
                                            ->pluck('model')
                                            ->mapWithKeys(fn (string $m): array => [$m => $m])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('compatibility_years', null);
                                    })
                                    ->required()
                                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make'))),
                                Forms\Components\TextInput::make('compatibility_years')
                                    ->label('Годы выпуска')
                                    ->required()
                                    ->placeholder('Например: 2015–2020 или 2019, 2020, 2021')
                                    ->disabled(fn (Get $get): bool => blank($get('vehicle_make')) || blank($get('vehicle_model')))
                                    ->helperText(function (Get $get): string {
                                        $opts = Vehicle::yearOptionsForMakeAndModel(
                                            $get('vehicle_make'),
                                            $get('vehicle_model')
                                        );
                                        $base = 'Несколько лет — через запятую, пробел или «;». Диапазон: 2015-2020 или 2015–2020 (длинное тире). Годы 1900–2100.';
                                        if ($opts === []) {
                                            return $base;
                                        }

                                        return $base.' В справочнике для этой пары: '.implode(', ', array_keys($opts)).'.';
                                    }),
                            ]),
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
