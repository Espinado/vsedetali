<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Authorization\StaffPermission;
use App\Filament\Concerns\ChecksStaffPermissions;
use App\Filament\Resources\OrderResource;
use App\Models\OrderStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    use ChecksStaffPermissions;

    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeOrderStatus')
                ->label('Сменить статус заказа')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => static::allow(StaffPermission::ORDERS_EDIT))
                ->form([
                    Select::make('status_id')
                        ->label('Статус заказа')
                        ->options(fn (): array => OrderStatus::query()
                            ->orderBy('sort')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->default(fn (): int => (int) $this->record->status_id),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['status_id' => (int) $data['status_id']]);
                    $this->record->refresh();
                    Notification::make()
                        ->title('Статус заказа обновлён')
                        ->success()
                        ->send();
                }),
        ];
    }
}
