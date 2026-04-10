<?php

namespace App\Filament\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Concerns\ChecksStaffPermissions;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    use ChecksStaffPermissions;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Покупатели';

    protected static ?string $modelLabel = 'Покупатель';

    protected static ?string $pluralModelLabel = 'Покупатели';

    protected static ?string $navigationGroup = 'Главная';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::allow(StaffPermission::CUSTOMERS_VIEW);
    }

    public static function canCreate(): bool
    {
        return static::allow(StaffPermission::CUSTOMERS_VIEW);
    }

    public static function canEdit($record): bool
    {
        return static::allow(StaffPermission::CUSTOMERS_VIEW);
    }

    public static function canDelete($record): bool
    {
        return static::allow(StaffPermission::CUSTOMERS_VIEW);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->maxLength(50)
                    ->nullable(),
                Forms\Components\TextInput::make('last_login_ip')
                    ->label('Последний IP входа')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (?User $record): bool => $record !== null)
                    ->helperText('Заполняется при входе. При блокировке этот IP тоже попадает в список блокировок (вместе с email).'),
                Forms\Components\DateTimePicker::make('blocked_at')
                    ->label('Заблокирован с')
                    ->seconds(false)
                    ->nullable()
                    ->helperText('Блокировка: запись в списке покупателей + автоматически email и последний IP (если известен) для входа и оформления заказа. Очистите поле, чтобы снять блокировку.'),
                Forms\Components\Textarea::make('block_reason')
                    ->label('Причина блокировки')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('last_login_ip')
                    ->label('Посл. IP')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('id')
                    ->label('Доступ')
                    ->formatStateUsing(fn ($state, \App\Models\User $record): string => $record->isBlocked() ? 'Заблокирован' : 'Активен')
                    ->badge()
                    ->color(fn (\App\Models\User $record): string => $record->isBlocked() ? 'danger' : 'success')
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->defaultSort('name')
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
