<?php

namespace App\Observers;

use App\Models\Seller;
use App\Services\SellerMarketplaceSuspensionService;

class SellerObserver
{
    /** @var array<int|string, string> */
    private array $statusBeforeUpdate = [];

    public function __construct(
        private readonly SellerMarketplaceSuspensionService $suspensionService
    ) {}

    public function updating(Seller $seller): void
    {
        if (! $seller->exists || ! $seller->isDirty('status')) {
            return;
        }

        $this->statusBeforeUpdate[$seller->getKey()] = (string) $seller->getOriginal('status');
    }

    public function updated(Seller $seller): void
    {
        if (! $seller->wasChanged('status')) {
            return;
        }

        $key = $seller->getKey();
        $previous = $this->statusBeforeUpdate[$key] ?? null;
        unset($this->statusBeforeUpdate[$key]);

        if ($previous === null) {
            return;
        }

        if ($seller->status === Seller::STATUS_SUSPENDED && $previous !== Seller::STATUS_SUSPENDED) {
            $this->suspensionService->suspend($seller);

            return;
        }

        if ($previous === Seller::STATUS_SUSPENDED && $seller->status === Seller::STATUS_ACTIVE) {
            $this->suspensionService->restoreAfterUnblock($seller);
        }
    }
}
