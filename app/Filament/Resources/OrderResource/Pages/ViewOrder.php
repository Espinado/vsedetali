<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\OrderStatus;
use App\Models\Shipment;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function setOrderStatus(string $slug): void
    {
        $status = OrderStatus::where('slug', $slug)->first();

        if ($status) {
            $this->record->update(['status_id' => $status->id]);
            $this->record->refresh();
            if ($slug === 'confirmed') {
                $this->markOrderPaymentAsPaid();
            }
        }
    }

    protected function markOrderPaymentAsPaid(): void
    {
        $payment = $this->record->latestPayment;
        if ($payment && $payment->status !== 'paid') {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
        }
    }

    protected function upsertShipmentStatus(string $status, array $attributes = []): void
    {
        $shipment = $this->record->latestShipment;

        if ($shipment) {
            $shipment->update(array_merge(['status' => $status], $attributes));
        } else {
            Shipment::create(array_merge([
                'order_id' => $this->record->id,
                'shipping_method_id' => $this->record->shipping_method_id,
                'status' => $status,
            ], $attributes));
        }

        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->label('Подтвердить')
                ->color('info')
                ->action(function (): void {
                    $this->setOrderStatus('confirmed');
                    Notification::make()->title('Заказ подтверждён')->success()->send();
                }),
            Actions\Action::make('pack')
                ->label('Собран')
                ->color('warning')
                ->action(function (): void {
                    $this->upsertShipmentStatus('packed');
                    $this->setOrderStatus('processing');

                    Notification::make()->title('Заказ отмечен как собранный')->success()->send();
                }),
            Actions\Action::make('ship')
                ->label('Отгрузить')
                ->color('primary')
                ->action(function (): void {
                    $this->upsertShipmentStatus('shipped', ['shipped_at' => now()]);
                    $this->setOrderStatus('shipped');

                    Notification::make()->title('Заказ отгружен')->success()->send();
                }),
            Actions\Action::make('deliver')
                ->label('Доставлен')
                ->color('success')
                ->action(function (): void {
                    $this->upsertShipmentStatus('delivered', [
                        'shipped_at' => $this->record->latestShipment?->shipped_at ?? now(),
                    ]);
                    $this->setOrderStatus('delivered');

                    Notification::make()->title('Заказ доставлен')->success()->send();
                }),
            Actions\Action::make('cancel')
                ->label('Отменить')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->upsertShipmentStatus('cancelled');
                    $this->setOrderStatus('cancelled');

                    Notification::make()->title('Заказ отменён')->success()->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
