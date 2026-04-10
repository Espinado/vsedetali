<?php

namespace App\Services;

use App\Models\CustomerBlock;
use App\Models\User;
use Illuminate\Support\Str;

class CustomerBlockingService
{
    public function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public function normalizeIp(string $ip): string
    {
        return trim($ip);
    }

    /**
     * MAC только для ручного занесения в блок-лист (в HTTP-запросе от браузера MAC не передаётся).
     */
    public function normalizeMac(string $mac): string
    {
        $s = Str::lower(preg_replace('/[^a-fA-F0-9]/', '', $mac) ?? '');
        if (strlen($s) !== 12) {
            return Str::lower(trim($mac));
        }

        return implode(':', str_split($s, 2));
    }

    public function isEmailBlocked(string $email): bool
    {
        $email = $this->normalizeEmail($email);
        if ($email === '') {
            return false;
        }

        if (User::query()->where('email', $email)->whereNotNull('blocked_at')->exists()) {
            return true;
        }

        return CustomerBlock::query()
            ->where('type', CustomerBlock::TYPE_EMAIL)
            ->where('value', $email)
            ->exists();
    }

    public function isIpBlocked(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return false;
        }

        $ip = $this->normalizeIp($ip);

        return CustomerBlock::query()
            ->where('type', CustomerBlock::TYPE_IP)
            ->where('value', $ip)
            ->exists();
    }

    public function isMacBlocked(?string $mac): bool
    {
        if ($mac === null || $mac === '') {
            return false;
        }

        $hex = preg_replace('/[^a-fA-F0-9]/', '', $mac);
        if (strlen($hex) !== 12) {
            return false;
        }

        $normalized = $this->normalizeMac($mac);

        return CustomerBlock::query()
            ->where('type', CustomerBlock::TYPE_MAC)
            ->where('value', $normalized)
            ->exists();
    }

    /**
     * Проверка при оформлении заказа и по сессии покупателя.
     *
     * @throws \RuntimeException
     */
    public function assertCheckoutAllowed(string $customerEmail, ?string $ip, ?string $macHeader = null): void
    {
        if ($this->isEmailBlocked($customerEmail)) {
            throw new \RuntimeException('Оформление заказа с этим email недоступно. Обратитесь в магазин.');
        }

        if ($this->isIpBlocked($ip)) {
            throw new \RuntimeException('Оформление заказа с вашего подключения временно недоступно. Обратитесь в магазин.');
        }

        if ($macHeader !== null && $this->isMacBlocked($macHeader)) {
            throw new \RuntimeException('Оформление заказа с этого устройства недоступно. Обратитесь в магазин.');
        }
    }

    /**
     * Синхронизация таблицы customer_blocks с блокировкой покупателя (Покупатели в админке).
     * При блокировке: email + последний известный IP входа (если есть).
     * При снятии блокировки: удаляются соответствующие записи email/ip.
     */
    public function syncBlacklistForCustomerUser(User $user): void
    {
        if ($user->isBlocked()) {
            $email = $this->normalizeEmail($user->email);

            if ($user->wasChanged('email') && $user->getOriginal('email')) {
                $oldEmail = $this->normalizeEmail((string) $user->getOriginal('email'));
                if ($oldEmail !== '' && $oldEmail !== $email) {
                    CustomerBlock::query()
                        ->where('type', CustomerBlock::TYPE_EMAIL)
                        ->where('value', $oldEmail)
                        ->delete();
                }
            }

            CustomerBlock::query()->updateOrCreate(
                ['type' => CustomerBlock::TYPE_EMAIL, 'value' => $email],
                ['reason' => $user->block_reason]
            );

            $ip = $user->last_login_ip ? $this->normalizeIp($user->last_login_ip) : null;
            if ($ip !== null && $ip !== '') {
                CustomerBlock::query()->updateOrCreate(
                    ['type' => CustomerBlock::TYPE_IP, 'value' => $ip],
                    ['reason' => $user->block_reason]
                );
            }

            return;
        }

        $email = $this->normalizeEmail($user->email);
        CustomerBlock::query()
            ->where('type', CustomerBlock::TYPE_EMAIL)
            ->where('value', $email)
            ->delete();

        $ip = $user->last_login_ip ? $this->normalizeIp($user->last_login_ip) : null;
        if ($ip !== null && $ip !== '') {
            CustomerBlock::query()
                ->where('type', CustomerBlock::TYPE_IP)
                ->where('value', $ip)
                ->delete();
        }
    }
}
