<button type="button" wire:click="openDrawer" class="relative inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-lg text-stone-300 transition hover:bg-stone-800 hover:text-orange-300" title="Корзина">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
    </svg>
    @if($this->count > 0)
        <span class="absolute -top-1.5 -right-1.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-orange-500 px-1 text-xs font-bold text-white shadow-sm shadow-orange-600/40">
            {{ $this->count > 99 ? '99+' : $this->count }}
        </span>
    @endif
</button>
