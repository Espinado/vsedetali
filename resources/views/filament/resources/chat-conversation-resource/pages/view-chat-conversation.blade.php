<x-filament-panels::page>
    <div class="space-y-6" data-admin-chat-view-page>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Клиент</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">
                        {{ $this->record->user?->email ?? 'Гость (без аккаунта)' }}
                    </dd>
                </div>
                @if($this->record->guest_token)
                    <div>
                        <dt class="font-medium text-gray-500 dark:text-gray-400">Токен гостя</dt>
                        <dd class="mt-0.5 font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $this->record->guest_token }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div id="admin-chat-thread-scroll" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 min-h-[16rem] max-h-[50vh] overflow-y-auto space-y-3">
            @foreach($this->record->messages as $message)
                <div class="flex {{ $message->sender === \App\Models\ChatMessage::SENDER_STAFF ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm {{ $message->sender === \App\Models\ChatMessage::SENDER_STAFF ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100' }}">
                        <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                        <p class="text-xs opacity-80 mt-1">
                            {{ $message->created_at->format('d.m.Y H:i') }}
                            @if($message->sender === \App\Models\ChatMessage::SENDER_STAFF && ($message->staff || $message->user))
                                — {{ $message->staff?->name ?? $message->user?->name }}
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        <form
            wire:submit="sendReply"
            x-data='{}'
            class="space-y-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <label for="chat-reply" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ответ менеджера</label>
            <textarea
                id="chat-reply"
                wire:key="admin-chat-reply-{{ $this->record->id }}"
                wire:model.live="replyBody"
                rows="4"
                class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:opacity-70 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:focus:border-primary-500"
                placeholder="Текст сообщения…"
                title="Enter — отправить, Shift+Enter — новая строка"
                x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendReply() }"
            ></textarea>
            @error('replyBody')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
            <x-filament::button type="submit">
                Отправить
            </x-filament::button>
        </form>
    </div>

    @script
    <script>
        const chatId = {{ $this->record->id }};
        let attempts = 0;

        const scrollAdminThreadToBottom = () => {
            const el = document.getElementById('admin-chat-thread-scroll');
            if (!el) {
                return;
            }
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight;
            });
        };

        const connect = () => {
            if (window.Echo) {
                window.Echo.private('chat.' + chatId).listen('.message.created', async () => {
                    await $wire.$refresh();
                    scrollAdminThreadToBottom();
                });
                return;
            }
            attempts += 1;
            if (attempts < 200) {
                setTimeout(connect, 50);
            }
        };
        connect();

        if (window.Livewire?.hook && !window.__adminChatViewMorphHooked) {
            window.__adminChatViewMorphHooked = true;
            window.Livewire.hook('morph.updated', ({ el }) => {
                if (!el?.closest?.('[data-admin-chat-view-page]')) {
                    return;
                }
                scrollAdminThreadToBottom();
            });
        }
    </script>
    @endscript
</x-filament-panels::page>
