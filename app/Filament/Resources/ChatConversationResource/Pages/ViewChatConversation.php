<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Resources\ChatConversationResource;
use App\Models\ChatMessage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewChatConversation extends ViewRecord
{
    protected static string $resource = ChatConversationResource::class;

    protected static string $view = 'filament.resources.chat-conversation-resource.pages.view-chat-conversation';

    public string $replyBody = '';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->markCustomerMessagesReadForStaff();
        $this->record->refresh();
        $this->record->load([
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user',
            'messages.staff',
        ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Чат №'.$this->record->id;
    }

    public function sendReply(): void
    {
        $this->validate([
            'replyBody' => 'required|string|min:1|max:10000',
        ]);

        ChatMessage::create([
            'conversation_id' => $this->record->id,
            'sender' => ChatMessage::SENDER_STAFF,
            'user_id' => null,
            'staff_id' => auth('staff')->id(),
            'body' => trim($this->replyBody),
        ]);

        $this->replyBody = '';
        $this->record->refresh();
        $this->record->load([
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user',
            'messages.staff',
        ]);

        Notification::make()->title('Сообщение отправлено')->success()->send();
    }
}
