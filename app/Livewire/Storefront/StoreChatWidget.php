<?php

namespace App\Livewire\Storefront;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\StoreChatService;
use Livewire\Component;

class StoreChatWidget extends Component
{
    public int $conversationId;

    public string $body = '';

    public bool $panelOpen = false;

    public function mount(StoreChatService $storeChatService): void
    {
        $conversation = $storeChatService->getOrCreateOpenConversation();
        $this->conversationId = $conversation->id;
    }

    public function togglePanel(): void
    {
        $this->panelOpen = ! $this->panelOpen;
    }

    public function sendMessage(): void
    {
        $this->validate([
            'body' => 'required|string|min:1|max:10000',
        ]);

        $conversation = ChatConversation::query()->findOrFail($this->conversationId);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => ChatMessage::SENDER_CUSTOMER,
            'user_id' => auth()->id(),
            'body' => trim($this->body),
        ]);

        $this->body = '';
    }

    public function render()
    {
        $messages = collect();
        $unreadStaffCount = 0;

        $conversation = ChatConversation::query()->find($this->conversationId);

        if ($conversation) {
            if ($this->panelOpen) {
                $conversation->markStaffMessagesReadForCustomer();
                $messages = $conversation->messages()->orderBy('created_at')->get();
            } else {
                $unreadStaffCount = $conversation->unreadStaffMessagesCountForCustomer();
            }
        }

        return view('livewire.storefront.store-chat-widget', [
            'messages' => $messages,
            'unreadStaffCount' => $unreadStaffCount,
        ]);
    }
}
