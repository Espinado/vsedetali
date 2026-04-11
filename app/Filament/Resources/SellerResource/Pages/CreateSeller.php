<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\SellerStaff;
use App\Models\Warehouse;
use App\Services\SellerStaffInvitationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected static bool $canCreateAnother = false;

    protected string $adminFirstName = '';

    protected string $adminLastName = '';

    protected string $adminEmail = '';

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
        $this->adminFirstName = trim((string) ($data['admin_first_name'] ?? ''));
        $this->adminLastName = trim((string) ($data['admin_last_name'] ?? ''));
        $this->adminEmail = trim((string) ($data['admin_email'] ?? ''));

        unset(
            $data['admin_first_name'],
            $data['admin_last_name'],
            $data['admin_email'],
        );

        if ($this->adminFirstName === '' || $this->adminLastName === '' || $this->adminEmail === '') {
            throw ValidationException::withMessages(['admin_email' => 'Заполните данные администратора продавца.']);
        }

        $base = Str::slug($data['name']);
        if ($base === '') {
            $base = 'seller';
        }
        $slug = $base;
        $i = 0;
        while (\App\Models\Seller::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }
        $data['slug'] = $slug;
        $data['user_id'] = null;
        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }

    protected function afterCreate(): void
    {
        $seller = $this->record;

        $staff = SellerStaff::query()->create([
            'seller_id' => $seller->id,
            'name' => trim($this->adminFirstName.' '.$this->adminLastName),
            'email' => $this->adminEmail,
            'password' => null,
        ]);
        $staff->assignRole('admin');

        app(SellerStaffInvitationService::class)->sendInvitation($staff);

        $code = 'SELLER-'.$seller->id;
        if (Warehouse::query()->where('code', $code)->exists()) {
            $code .= '-'.Str::lower(Str::random(4));
        }
        Warehouse::query()->create([
            'seller_id' => $seller->id,
            'name' => 'Основной склад',
            'code' => $code,
            'is_default' => false,
            'is_active' => true,
        ]);

        FilamentSweetAlert::flashSuccess('Продавец создан. Приглашение отправлено на '.$this->adminEmail);
    }
}
