<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Filament\Resources\SellerResource\RelationManagers\MarketplaceSellerProductsRelationManager;
use App\Filament\Resources\SellerResource\RelationManagers\WarehousesRelationManager;
use App\Models\Seller;
use App\Models\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Продавцы';

    protected static ?string $modelLabel = 'Продавец';

    protected static ?string $pluralModelLabel = 'Продавцы';

    protected static ?string $navigationGroup = 'Склад';

    protected static ?int $navigationSort = 12;

    public static function shouldRegisterNavigation(): bool
    {
        return static::staffIsAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::staffIsAdmin();
    }

    public static function canCreate(): bool
    {
        return static::staffIsAdmin();
    }

    public static function canEdit($record): bool
    {
        return static::staffIsAdmin();
    }

    public static function canDelete($record): bool
    {
        return static::staffIsAdmin();
    }

    protected static function staffIsAdmin(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof Staff && $user->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Компания')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Код (slug)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('При создании формируется из названия автоматически.')
                            ->visibleOn('edit'),
                        Forms\Components\DatePicker::make('contract_date')
                            ->label('Дата договора')
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('commission_percent')
                            ->label('Комиссия площадки, %')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01),
                        Forms\Components\Select::make('status')
                            ->label('Статус')
                            ->options([
                                'pending' => 'Ожидает',
                                'active' => 'Активен',
                                'suspended' => 'Приостановлен',
                                'rejected' => 'Отклонён',
                            ])
                            ->default('active')
                            ->required()
                            ->visibleOn('edit'),
                        Forms\Components\TextInput::make('inn')
                            ->label('ИНН')
                            ->maxLength(50)
                            ->nullable(),
                        Forms\Components\Textarea::make('legal_info')
                            ->label('Юр. информация')
                            ->rows(3)
                            ->nullable(),
                    ]),
                Forms\Components\Section::make('Администратор продавца (первый вход по приглашению)')
                    ->description('На email уйдёт ссылка для установки пароля. Вход в кабинет: '.url('/seller').'. После создания автоматически создаётся склад продавца «Основной склад». Дополнительные склады и товары на площадке — во вкладках карточки продавца (после сохранения).')
                    ->visibleOn('create')
                    ->schema([
                        Forms\Components\TextInput::make('admin_first_name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('admin_last_name')
                            ->label('Фамилия')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('admin_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
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
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contract_date')
                    ->label('Договор')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_percent')
                    ->label('Комиссия %')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ожидает',
                        'active' => 'Активен',
                        'suspended' => 'Приостановлен',
                        'rejected' => 'Отклонён',
                        default => $state,
                    }),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('sellerStaff');
    }

    public static function getRelations(): array
    {
        return [
            WarehousesRelationManager::class,
            MarketplaceSellerProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
