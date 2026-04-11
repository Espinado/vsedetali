<?php

namespace App\Services;

use App\Models\Seller;
use App\Models\SellerProduct;
use Illuminate\Support\Facades\DB;

/**
 * Блокировка продавца (status = suspended): пауза активных листингов и снятие товаров с витрины.
 * Разблокировка (→ active): восстановление только тех листингов, что были активны до блокировки.
 */
final class SellerMarketplaceSuspensionService
{
    public function suspend(Seller $seller): void
    {
        DB::transaction(function () use ($seller): void {
            SellerProduct::query()
                ->where('seller_id', $seller->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->cursor()
                ->each(function (SellerProduct $listing): void {
                    $listing->update([
                        'blocked_restore_marketplace_status' => 'active',
                        'status' => 'paused',
                    ]);
                    $listing->product?->update(['is_active' => false]);
                });
        });
    }

    public function restoreAfterUnblock(Seller $seller): void
    {
        DB::transaction(function () use ($seller): void {
            SellerProduct::query()
                ->where('seller_id', $seller->id)
                ->whereNotNull('blocked_restore_marketplace_status')
                ->orderBy('id')
                ->cursor()
                ->each(function (SellerProduct $listing): void {
                    $restore = (string) $listing->blocked_restore_marketplace_status;
                    $listing->update([
                        'status' => $restore,
                        'blocked_restore_marketplace_status' => null,
                    ]);
                    if ($restore === 'active') {
                        $listing->product?->update(['is_active' => true]);
                    }
                });
        });
    }
}
