<?php

namespace App\Filament\Seller\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Seller\Resources\SellerStaffResource\Pages;
use App\Models\SellerStaff;
use App\Services\SellerStaffInvitationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SellerStaffResource extends Resource
{
    protected static ?string $model = SellerStaff::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Персонал';

    protected static ?string $modelLabel = 'Сотрудник';

    protected static ?string $pluralModelLabel = 'Персонал';

    protected static ?int $navigationSort = 20;

    protected static function staff(): ?SellerStaff
    {
        $u = auth('seller_staff')->user();

        return $u instanceof SellerStaff ? $u : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $s = static::staff();

        return $s !== null && $s->can(StaffPermission::STAFF_MANAGE);
    }

    public static function canViewAny(): bool
    {
        $s = static::staff();

        return $s !== null && $s->can(StaffPermission::STAFF_MANAGE);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        $s = static::staff();

        return $s !== null
            && $s->can(StaffPermission::STAFF_MANAGE)
            && $record instanceof SellerStaff
            && $record->id !== $s->id;
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
                    ->description('Пароль сотрудник задаёт по ссылке из письма.')
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
                                fn ($query) => $query->where('guard_name', 'seller_staff')
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
                    ->formatStateUsing(fn ($state, SellerStaff $record): string => $record->hasPasswordSet() ? 'Задан' : 'Ожидает установки')
                    ->badge()
                    ->color(fn (SellerStaff $record): string => $record->hasPasswordSet() ? 'success' : 'warning')
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->defaultSort('name')
            ->actions([
                TableAction::make('resendInvitation')
                    ->label('Выслать письмо повторно')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn (SellerStaff $record): bool => ! $record->hasPasswordSet())
                    ->action(function (SellerStaff $record): void {
                        app(SellerStaffInvitationService::class)->sendInvitation($record);
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
            'index' => Pages\ListSellerStaff::route('/'),
            'create' => Pages\CreateSellerStaff::route('/create'),
            'edit' => Pages\EditSellerStaff::route('/{record}/edit'),
        ];
    }
}
