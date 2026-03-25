<?php

namespace App\Models;

use App\Events\ChatMessageCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    public const SENDER_CUSTOMER = 'customer';

    public const SENDER_STAFF = 'staff';

    protected $fillable = [
        'conversation_id',
        'sender',
        'user_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (ChatMessage $message): void {
            $message->conversation?->touchLastMessageAt();
            ChatMessageCreated::dispatch($message);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
