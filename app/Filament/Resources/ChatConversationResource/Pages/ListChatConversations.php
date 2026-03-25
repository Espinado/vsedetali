<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Resources\ChatConversationResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;

class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    #[On('filament-chat-list-refresh')]
    public function refreshChatListFromBroadcast(): void
    {
        // Перерисовка таблицы после события из Echo (filament-admin-chat.js)
    }

    public function getSubheading(): ?string
    {
        return 'Входящие сообщения с витрины. Откройте строку — внизу страницы переписки введите ответ и нажмите «Отправить».';
    }
}
