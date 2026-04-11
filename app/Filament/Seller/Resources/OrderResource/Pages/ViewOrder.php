<?php

namespace App\Filament\Seller\Resources\OrderResource\Pages;

use App\Authorization\StaffPermission;
use App\Filament\Concerns\ManagesOrderShipmentAndStatus;
use App\Filament\Seller\Resources\OrderResource;
use App\Filament\Support\FilamentSweetAlert;
use App\Models\SellerStaff;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Facades\FilamentView;

class ViewOrder extends ViewRecord
{
    use ManagesOrderShipmentAndStatus;

    protected static string $resource = OrderResource::class;

    protected function sellerStaff(): ?SellerStaff
    {
        $u = auth('seller_staff')->user();

        return $u instanceof SellerStaff ? $u : null;
    }

    protected function sellerCanManageFulfillment(): bool
    {
        $s = $this->sellerStaff();
        if ($s === null) {
            return false;
        }

        return $s->can(StaffPermission::ORDERS_EDIT) || $s->can(StaffPermission::SHIPMENTS_MANAGE);
    }

    protected function orderFulfillmentRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        if (! $this->sellerCanManageFulfillment()) {
            return [];
        }

        return [
            Actions\Action::make('to_processing')
                ->label('В обработку')
                ->color('gray')
                ->visible(fn (): bool => in_array($this->record->status?->slug, ['new', 'confirmed'], true))
                ->action(function (): void {
                    $this->upsertShipmentStatus('pending');
                    $this->setOrderStatus('processing', false);
                    FilamentSweetAlert::flashSuccess('Заказ взят в обработку');
                    $url = $this->orderFulfillmentRedirectUrl();
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('pack')
                ->label('Собран')
                ->color('warning')
                ->action(function (): void {
                    $this->upsertShipmentStatus('packed');
                    $this->setOrderStatus('processing', false);
                    FilamentSweetAlert::flashSuccess('Заказ отмечен как собранный');
                    $url = $this->orderFulfillmentRedirectUrl();
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('ship')
                ->label('Отгрузить')
                ->color('primary')
                ->action(function (): void {
                    $this->upsertShipmentStatus('shipped', ['shipped_at' => now()]);
                    $this->setOrderStatus('shipped', false);
                    FilamentSweetAlert::flashSuccess('Заказ отгружен');
                    $url = $this->orderFulfillmentRedirectUrl();
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('deliver')
                ->label('Доставлен')
                ->color('success')
                ->action(function (): void {
                    $this->upsertShipmentStatus('delivered', [
                        'shipped_at' => $this->record->latestShipment?->shipped_at ?? now(),
                    ]);
                    $this->setOrderStatus('delivered', false);
                    FilamentSweetAlert::flashSuccess('Заказ доставлен');
                    $url = $this->orderFulfillmentRedirectUrl();
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
            Actions\Action::make('cancel')
                ->label('Отменить')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->upsertShipmentStatus('cancelled');
                    $this->setOrderStatus('cancelled', false);
                    FilamentSweetAlert::flashSuccess('Заказ отменён');
                    $url = $this->orderFulfillmentRedirectUrl();
                    $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
                }),
        ];
    }
}
