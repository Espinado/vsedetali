<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Services\StaffInvitationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Создать и выслать приглашение');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Отмена');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(StaffInvitationService::class)->sendInvitation($this->record);

        Notification::make()
            ->title('Приглашение отправлено на '.$this->record->email)
            ->success()
            ->send();
    }
}
