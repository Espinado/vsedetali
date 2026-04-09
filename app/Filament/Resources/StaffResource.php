<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\Staff;
use App\Services\StaffInvitationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $model = Staff::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationLabel = 'Персонал';

    protected static ?string $modelLabel = 'Сотрудник';

    protected static ?string $pluralModelLabel = 'Сотрудники';

    protected static ?string $navigationGroup = 'Главная';

    protected static ?int $navigationSort = 0;

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
                Forms\Components\Section::make()
                    ->description('Пароль задаётся сотрудником по ссылке из письма. При создании письмо уходит автоматически; для повторной отправки — кнопка в таблице или в карточке сотрудника.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                fn ($query) => $query->where('guard_name', 'staff')
                            )
                            ->columns(2)
                            ->required()
                            ->label('Роли'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label('Роли'),
                Tables\Columns\TextColumn::make('id')
                    ->label('Пароль')
                    ->formatStateUsing(fn ($state, Staff $record): string => $record->hasPasswordSet() ? 'Задан' : 'Ожидает установки')
                    ->badge()
                    ->color(fn (Staff $record): string => $record->hasPasswordSet() ? 'success' : 'warning')
                    ->sortable(false)
                    ->searchable(false),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                TableAction::make('resendInvitation')
                    ->label('Выслать письмо повторно')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->modalHeading('Отправить письмо для ввода пароля?')
                    ->modalDescription('На email сотрудника будет отправлена новая ссылка. Старая ссылка перестанет действовать.')
                    ->action(function (Staff $record): void {
                        app(StaffInvitationService::class)->sendInvitation($record);
                        Notification::make()
                            ->title('Письмо отправлено на '.$record->email)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
