<?php

namespace App\Models;

use App\Events\ChatCustomerMessagesReadByStaff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'guest_token',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChatConversation $conversation): void {
            if ($conversation->uuid === null) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function unreadCustomerMessagesCount(): int
    {
        return $this->messages()
            ->where('sender', ChatMessage::SENDER_CUSTOMER)
            ->whereNull('read_at')
            ->count();
    }

    /** Непрочитанные ответы магазина (для бейджа на витрине, пока чат закрыт). */
    public function unreadStaffMessagesCountForCustomer(): int
    {
        return $this->messages()
            ->where('sender', ChatMessage::SENDER_STAFF)
            ->whereNull('read_at')
            ->count();
    }

    public function markCustomerMessagesReadForStaff(): void
    {
        $updated = $this->messages()
            ->where('sender', ChatMessage::SENDER_CUSTOMER)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updated > 0) {
            ChatCustomerMessagesReadByStaff::dispatch($this->id);
        }
    }

    public function markStaffMessagesReadForCustomer(): void
    {
        $this->messages()
            ->where('sender', ChatMessage::SENDER_STAFF)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function touchLastMessageAt(): void
    {
        $this->update(['last_message_at' => now()]);
    }

    /** Диалоги, где последнее сообщение от клиента (ожидают ответа оператора). */
    public static function conversationsAwaitingStaffReplyCount(): int
    {
        return (int) static::query()
            ->whereHas('latestMessage', fn (Builder $q) => $q->where('sender', ChatMessage::SENDER_CUSTOMER))
            ->count();
    }
}
