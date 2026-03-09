<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Настройки';
    protected static ?string $title = 'Настройки магазина';
    protected static ?string $slug = 'settings';
    protected static ?string $navigationGroup = 'Система';
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'store_name' => Setting::get('store_name', config('app.name')),
            'store_email' => Setting::get('store_email', ''),
            'store_phone' => Setting::get('store_phone', ''),
            'currency' => Setting::get('currency', 'EUR'),
            'orders_notify_email' => Setting::get('orders_notify_email', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Основные')
                    ->schema([
                        Forms\Components\TextInput::make('store_name')
                            ->label('Название магазина')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('store_email')
                            ->label('Email магазина')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('store_phone')
                            ->label('Телефон')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('currency')
                            ->label('Валюта (код)')
                            ->maxLength(10)
                            ->default('EUR'),
                    ]),
                Forms\Components\Section::make('Заказы')
                    ->schema([
                        Forms\Components\TextInput::make('orders_notify_email')
                            ->label('Email для уведомлений о заказах')
                            ->email()
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        Setting::set('store_name', $data['store_name'] ?? '');
        Setting::set('store_email', $data['store_email'] ?? '', 'general');
        Setting::set('store_phone', $data['store_phone'] ?? '', 'general');
        Setting::set('currency', $data['currency'] ?? 'EUR', 'general');
        Setting::set('orders_notify_email', $data['orders_notify_email'] ?? '', 'orders');
        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Сохранить')
                ->submit('save'),
        ];
    }
}
