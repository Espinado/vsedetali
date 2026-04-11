<?php

namespace App\Filament\Concerns;

use App\Models\OrderStatus;
use App\Models\Shipment;

/**
 * Смена статуса заказа и отгрузки (кнопки «Собран», «Отгрузить» и т.д.).
 */
trait ManagesOrderShipmentAndStatus
{
    protected function setOrderStatus(string $slug, bool $markPaidWhenConfirmed = false): void
    {
        $status = OrderStatus::where('slug', $slug)->first();

        if ($status) {
            $this->record->update(['status_id' => $status->id]);
            $this->record->refresh();
            if ($slug === 'confirmed' && $markPaidWhenConfirmed) {
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
}
