<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
        $this->message->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->message->conversation_id),
            // Все сообщения — чтобы список чатов в админке обновлялся в реальном времени
            new PrivateChannel('admin.chat'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender' => $this->message->sender,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at?->toIso8601String(),
                'user_name' => $this->message->user?->name,
            ],
            'unread_customer_messages' => ChatMessage::query()
                ->where('sender', ChatMessage::SENDER_CUSTOMER)
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
