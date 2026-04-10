<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CustomerBlockingService;

class UserObserver
{
    public function saved(User $user): void
    {
        $blocking = app(CustomerBlockingService::class);

        if ($user->isBlocked()) {
            if (! $user->wasChanged(['blocked_at', 'block_reason', 'email', 'last_login_ip'])) {
                return;
            }
            $blocking->syncBlacklistForCustomerUser($user);

            return;
        }

        if ($user->wasChanged('blocked_at') && $user->getOriginal('blocked_at') !== null) {
            $blocking->syncBlacklistForCustomerUser($user);
        }
    }
}
