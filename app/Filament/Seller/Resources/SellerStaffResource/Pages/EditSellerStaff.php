<?php

namespace App\Filament\Seller\Resources\SellerStaffResource\Pages;

use App\Filament\Seller\Resources\SellerStaffResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Services\SellerStaffInvitationService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentView;

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
                    FilamentSweetAlert::flashSuccess('Письмо отправлено на '.$this->record->email);
                    $url = SellerStaffResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
