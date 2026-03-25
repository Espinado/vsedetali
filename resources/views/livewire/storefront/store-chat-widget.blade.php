<div class="fixed bottom-4 right-4 z-[60] flex flex-col items-end gap-2" data-store-chat-widget x-data x-on:keydown.escape.window="$wire.set('panelOpen', false)">
    @if($panelOpen)
        <div class="w-[min(100vw-2rem,22rem)] sm:w-[22rem] max-h-[min(70vh,28rem)] flex flex-col rounded-lg border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-200 bg-slate-50 rounded-t-lg">
                <span class="text-sm font-semibold text-slate-800">Чат с магазином</span>
                <button type="button" wire:click="togglePanel" class="p-1 rounded text-slate-500 hover:bg-slate-200 hover:text-slate-800" title="Закрыть">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="store-chat-scroll" class="flex-1 overflow-y-auto px-3 py-3 space-y-2 min-h-[12rem] max-h-[min(50vh,20rem)]" wire:key="chat-messages-{{ $conversationId }}">
                @forelse($messages as $message)
                    <div class="flex {{ $message->sender === \App\Models\ChatMessage::SENDER_STAFF ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm {{ $message->sender === \App\Models\ChatMessage::SENDER_STAFF ? 'bg-slate-100 text-slate-900' : 'bg-slate-800 text-white' }}">
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            <p class="mt-1 text-[0.65rem] opacity-70">{{ $message->created_at->format('d.m H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 text-center py-6">Напишите вопрос — менеджер ответит в рабочее время.</p>
                @endforelse
            </div>
            <form wire:submit="sendMessage" class="border-t border-slate-200 p-2 flex gap-2">
                <label class="sr-only" for="store-chat-input">Сообщение</label>
                <textarea
                    id="store-chat-input"
                    wire:model="body"
                    rows="2"
                    class="flex-1 rounded border border-slate-300 text-sm px-2 py-1.5 focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                    placeholder="Сообщение…"
                    title="Enter — отправить, Shift+Enter — новая строка"
                    x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendMessage() }"
                ></textarea>
                <button type="submit" class="shrink-0 self-end px-3 py-2 rounded bg-slate-800 text-white text-sm font-medium hover:bg-slate-900 disabled:opacity-50" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="sendMessage">Отпр.</span>
                    <span wire:loading wire:target="sendMessage">…</span>
                </button>
            </form>
        </div>
    @endif

    <button
        type="button"
        wire:click="togglePanel"
        class="relative inline-flex items-center justify-center w-14 h-14 rounded-full bg-slate-800 text-white shadow-lg hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-600"
        title="Чат с магазином"
        aria-expanded="{{ $panelOpen ? 'true' : 'false' }}"
        @if(! $panelOpen && $unreadStaffCount > 0)
            aria-label="Чат: {{ $unreadStaffCount }} непрочитанных сообщений от магазина"
        @endif
    >
        @if(! $panelOpen && $unreadStaffCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-red-600 px-1 text-[0.625rem] font-bold leading-none text-white ring-2 ring-white tabular-nums">
                {{ $unreadStaffCount > 99 ? '99+' : $unreadStaffCount }}
            </span>
        @endif
        @if($panelOpen)
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        @else
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        @endif
    </button>

    @script
    <script>
        const cid = {{ $conversationId }};
        let attempts = 0;

        const scrollStoreChatToBottom = () => {
            const el = document.getElementById('store-chat-scroll');
            if (!el) {
                return;
            }
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight;
            });
        };

        const connect = () => {
            if (!window.Echo) {
                attempts += 1;
                if (attempts < 200) {
                    setTimeout(connect, 50);
                }
                return;
            }

            window.Echo.private('chat.' + cid).listen('.message.created', async () => {
                await $wire.$refresh();
                scrollStoreChatToBottom();
            });
        };

        connect();

        if (window.Livewire?.hook && !window.__storeChatMorphHooked) {
            window.__storeChatMorphHooked = true;
            window.Livewire.hook('morph.updated', ({ el }) => {
                if (!el?.closest?.('[data-store-chat-widget]')) {
                    return;
                }
                scrollStoreChatToBottom();
            });
        }

        return () => {
            try {
                if (window.Echo) {
                    window.Echo.leave('chat.' + cid);
                }
            } catch (e) {
                /* ignore */
            }
        };
    </script>
    @endscript
</div>
