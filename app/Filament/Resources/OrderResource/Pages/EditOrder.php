<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\OrderStatus;
use App\Models\Shipment;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentView;

class EditOrder extends EditRecord
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

    protected function afterSave(): void
    {
        $confirmedStatus = OrderStatus::where('slug', 'confirmed')->first();
        if ($confirmedStatus && $this->record->status_id === $confirmedStatus->id) {
            $this->markOrderPaymentAsPaid();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm')
                ->label('Подтвердить')
                ->color('info')
                ->action(function (): void {
                    $this->setOrderStatus('confirmed');
                    FilamentSweetAlert::flashSuccess('Заказ подтверждён');
                    $url = OrderResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('pack')
                ->label('Собран')
                ->color('warning')
                ->action(function (): void {
                    $this->upsertShipmentStatus('packed');
                    $this->setOrderStatus('processing');
                    FilamentSweetAlert::flashSuccess('Заказ отмечен как собранный');
                    $url = OrderResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('ship')
                ->label('Отгрузить')
                ->color('primary')
                ->action(function (): void {
                    $this->upsertShipmentStatus('shipped', ['shipped_at' => now()]);
                    $this->setOrderStatus('shipped');
                    FilamentSweetAlert::flashSuccess('Заказ отгружен');
                    $url = OrderResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('deliver')
                ->label('Доставлен')
                ->color('success')
                ->action(function (): void {
                    $this->upsertShipmentStatus('delivered', [
                        'shipped_at' => $this->record->latestShipment?->shipped_at ?? now(),
                    ]);
                    $this->setOrderStatus('delivered');
                    FilamentSweetAlert::flashSuccess('Заказ доставлен');
                    $url = OrderResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('cancel')
                ->label('Отменить')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->upsertShipmentStatus('cancelled');
                    $this->setOrderStatus('cancelled');
                    FilamentSweetAlert::flashSuccess('Заказ отменён');
                    $url = OrderResource::getUrl('edit', ['record' => $this->record]);
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
        ];
    }
}
