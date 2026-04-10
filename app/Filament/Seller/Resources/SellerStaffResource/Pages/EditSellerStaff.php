<?php

namespace App\Filament\Seller\Resources\SellerStaffResource\Pages;

use App\Filament\Seller\Resources\SellerStaffResource;
use App\Services\SellerStaffInvitationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSellerStaff extends EditRecord
{
    protected static string $resource = SellerStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendInvitation')
                ->label('Выслать приглашение повторно')
                ->icon('heroicon-o-envelope')
                ->requiresConfirmation()
                ->visible(fn (): bool => ! $this->record->hasPasswordSet())
                ->action(function (): void {
                    app(SellerStaffInvitationService::class)->sendInvitation($this->record);
                    Notification::make()
                        ->title('Письмо отправлено на '.$this->record->email)
                        ->success()
                        ->send();
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
