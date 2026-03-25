<?php

namespace App\Services;

use App\Models\ChatConversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class StoreChatService
{
    public const COOKIE_NAME = 'store_chat_token';

    /** Ключ сессии для гостя (дублирует cookie — для /broadcasting/auth, если cookie не ушёл). */
    public const SESSION_KEY = 'store_chat_token';

    public function getOrCreateOpenConversation(): ChatConversation
    {
        if (Auth::check()) {
            session()->forget(self::SESSION_KEY);

            $existing = ChatConversation::query()
                ->where('user_id', Auth::id())
                ->where('status', 'open')
                ->first();

            if ($existing) {
                return $existing;
            }

            return ChatConversation::create([
                'user_id' => Auth::id(),
                'status' => 'open',
            ]);
        }

        $token = request()->cookie(self::COOKIE_NAME) ?? session(self::SESSION_KEY);

        if ($token) {
            $guestConversation = ChatConversation::query()
                ->where('guest_token', $token)
                ->where('status', 'open')
                ->first();

            if ($guestConversation) {
                session([self::SESSION_KEY => $token]);

                return $guestConversation;
            }
        }

        $token = Str::random(40);
        Cookie::queue(cookie(self::COOKIE_NAME, $token, 60 * 24 * 90));
        session([self::SESSION_KEY => $token]);

        return ChatConversation::create([
            'guest_token' => $token,
            'status' => 'open',
        ]);
    }
}
