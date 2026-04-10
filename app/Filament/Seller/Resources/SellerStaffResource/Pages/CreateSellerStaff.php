<?php

namespace App\Filament\Seller\Resources\SellerStaffResource\Pages;

use App\Filament\Seller\Resources\SellerStaffResource;
use App\Models\SellerStaff;
use App\Services\SellerStaffInvitationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSellerStaff extends CreateRecord
{
    protected static string $resource = SellerStaffResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Создать и выслать приглашение');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $staff = auth('seller_staff')->user();
        if (! $staff instanceof SellerStaff) {
            throw ValidationException::withMessages(['seller' => 'Нет доступа.']);
        }

        $data['seller_id'] = $staff->seller_id;
        $data['password'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SellerStaffInvitationService::class)->sendInvitation($this->record);

        Notification::make()
            ->title('Приглашение отправлено на '.$this->record->email)
            ->success()
            ->send();
    }
}
