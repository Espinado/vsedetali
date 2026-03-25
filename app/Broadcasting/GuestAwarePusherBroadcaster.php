<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * В PusherBroadcaster::auth() перед routes/channels.php стоит проверка retrieveUser() для любого private-*.
 * Она даёт 403, если $request->user() пустой — даже когда канал допускает гостя (chat.{id}) или когда
 * сессия админа на POST /broadcasting/auth не совпадает с ожиданием фреймворка.
 * Авторизация остаётся только в Broadcast::channel (в т.ч. is_admin для admin.chat).
 */
class GuestAwarePusherBroadcaster extends PusherBroadcaster
{
    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name)) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }
}
