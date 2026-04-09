<?php

namespace App\Filament\Resources;

use App\Authorization\StaffPermission;
use App\Filament\Concerns\ChecksStaffPermissions;
use App\Filament\Resources\CustomerBlockResource\Pages;
use App\Models\CustomerBlock;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerBlockResource extends Resource
{
    use ChecksStaffPermissions;

    protected static ?string $model = CustomerBlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Блок-лист';

    protected static ?string $modelLabel = 'Запись блок-листа';

    protected static ?string $pluralModelLabel = 'Блок-лист (email / IP / MAC)';

    protected static ?string $navigationGroup = 'Главная';

    protected static ?int $navigationSort = 2;

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
                Forms\Components\Select::make('type')
                    ->label('Тип')
                    ->options([
                        CustomerBlock::TYPE_EMAIL => 'Email',
                        CustomerBlock::TYPE_IP => 'IP-адрес',
                        CustomerBlock::TYPE_MAC => 'MAC-адрес (только ручной ввод)',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('value')
                    ->label('Значение')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Для MAC: формат вроде aa:bb:cc:dd:ee:ff. В браузере MAC не передаётся; проверка по MAC сработает только при передаче заголовка X-Device-Mac (например, мобильное приложение).')
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $type = $get('type');
                            if ($type === CustomerBlock::TYPE_EMAIL && ! filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                                $fail('Укажите корректный email.');
                            }
                            if ($type === CustomerBlock::TYPE_IP && ! filter_var(trim((string) $value), FILTER_VALIDATE_IP)) {
                                $fail('Укажите корректный IP-адрес.');
                            }
                            if ($type === CustomerBlock::TYPE_MAC) {
                                $hex = preg_replace('/[^a-fA-F0-9]/', '', (string) $value);
                                if (strlen($hex) !== 12) {
                                    $fail('MAC должен содержать 12 шестнадцатеричных цифр.');
                                }
                            }
                        },
                    ]),
                Forms\Components\Textarea::make('reason')
                    ->label('Комментарий')
                    ->rows(2)
                    ->maxLength(2000),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        CustomerBlock::TYPE_EMAIL => 'Email',
                        CustomerBlock::TYPE_IP => 'IP',
                        CustomerBlock::TYPE_MAC => 'MAC',
                        default => $state,
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('value')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('reason')->limit(40)->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerBlocks::route('/'),
            'create' => Pages\CreateCustomerBlock::route('/create'),
            'edit' => Pages\EditCustomerBlock::route('/{record}/edit'),
        ];
    }
}
