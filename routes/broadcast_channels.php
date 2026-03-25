<?php

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\StoreChatService;
use Illuminate\Broadcasting\BroadcastManager;

// Явно на тот же драйвер, что и Broadcast::auth() (default), не через фасад.
$manager = app(BroadcastManager::class);
$broadcaster = $manager->connection(config('broadcasting.default', 'reverb'));

// Filament / уведомления: private-App.Models.User.{id}
$broadcaster->channel('App.Models.User.{id}', function (?User $user, string $id) {
    return $user && (int) $user->id === (int) $id
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
}, ['guards' => ['web']]);

$broadcaster->channel('admin.chat', function (?User $user) {
    return $user && $user->is_admin ? ['id' => $user->id, 'name' => $user->name] : false;
}, ['guards' => ['web']]);

$broadcaster->channel('chat.{conversationId}', function (?User $user, string $conversationId) {
    $conversation = ChatConversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    if ($user?->is_admin) {
        return true;
    }

    if ($user && (int) $conversation->user_id === (int) $user->id) {
        return true;
    }

    $token = request()->cookie(StoreChatService::COOKIE_NAME)
        ?? session(StoreChatService::SESSION_KEY);

    return $conversation->guest_token
        && $token
        && hash_equals((string) $conversation->guest_token, (string) $token);
}, ['guards' => ['web']]);
