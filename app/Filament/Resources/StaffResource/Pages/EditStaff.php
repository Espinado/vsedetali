<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Services\StaffInvitationService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resendInvitation')
                ->label('Выслать письмо для ввода пароля')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->modalHeading('Отправить письмо повторно?')
                ->modalDescription('На email сотрудника будет отправлена новая ссылка для установки или смены пароля. Старая ссылка перестанет действовать.')
                ->action(function (): void {
                    app(StaffInvitationService::class)->sendInvitation($this->record);
                    Notification::make()
                        ->title('Письмо отправлено на '.$this->record->email)
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
