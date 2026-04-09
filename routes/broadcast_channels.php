<?php

use App\Models\ChatConversation;
use App\Models\Staff;
use App\Models\User;
use App\Services\StoreChatService;
use Illuminate\Broadcasting\BroadcastManager;

// Явно на тот же драйвер, что и Broadcast::auth() (default), не через фасад.
$manager = app(BroadcastManager::class);
$broadcaster = $manager->connection(config('broadcasting.default', 'reverb'));

// Уведомления покупателю (витрина): private-App.Models.User.{id}
$broadcaster->channel('App.Models.User.{id}', function (?User $user, string $id) {
    return $user && (int) $user->id === (int) $id
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
}, ['guards' => ['web']]);

// Панель: авторизация через auth('staff') внутри callback (сессия общая с middleware web).
$broadcaster->channel('admin.chat', function () {
    $staff = auth('staff')->user();

    return $staff instanceof Staff && $staff->can('chat.manage')
        ? ['id' => $staff->id, 'name' => $staff->name]
        : false;
});

$broadcaster->channel('chat.{conversationId}', function ($user, string $conversationId) {
    $conversation = ChatConversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    $staff = auth('staff')->user();
    if ($staff instanceof Staff && $staff->can('chat.manage')) {
        return true;
    }

    if ($user instanceof User && (int) $conversation->user_id === (int) $user->id) {
        return true;
    }

    $token = request()->cookie(StoreChatService::COOKIE_NAME)
        ?? session(StoreChatService::SESSION_KEY);

    return $conversation->guest_token
        && $token
        && hash_equals((string) $conversation->guest_token, (string) $token);
});
