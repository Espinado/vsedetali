<div @class(['add-to-cart-store', 'add-to-cart-store--compact' => $compact])>
    <div @class([
        'add-to-cart-store__badge flex w-full items-stretch gap-2 transition',
        'rounded-lg px-2 py-1.5' => $compact,
        'rounded-xl px-2.5 py-2 sm:gap-3 sm:px-3 sm:py-2.5' => ! $compact,
    ])>
        <button type="button"
                wire:click="addToCart"
                wire:loading.attr="disabled"
                aria-label="Добавить в корзину"
                title="Добавить в корзину"
                @class([
                    'group flex shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700 ring-1 ring-orange-200/80 transition hover:bg-orange-200/80 disabled:pointer-events-none disabled:opacity-50',
                    'h-8 w-8' => $compact,
                    'h-10 w-10' => ! $compact,
                ])>
            <span wire:loading.remove wire:target="addToCart" @class(['text-current', 'scale-90' => $compact]) aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" @class(['h-4 w-4' => $compact, 'h-5 w-5' => ! $compact])>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.267 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 005.513 7.5h12.974a1.125 1.125 0 011.119 1.007z" />
                </svg>
            </span>
            <span wire:loading wire:target="addToCart" @class(['inline-block animate-spin rounded-full border-2 border-orange-600 border-t-transparent', 'h-3.5 w-3.5' => $compact, 'h-4 w-4' => ! $compact]) aria-hidden="true"></span>
        </button>
        <div class="flex min-w-0 flex-1 items-center gap-1.5">
            <label for="qty-{{ $product->id }}" @class(['shrink-0 text-slate-500', 'text-[11px]' => $compact, 'text-xs' => ! $compact])>Кол-во</label>
            <input type="number"
                   id="qty-{{ $product->id }}"
                   min="1"
                   max="99"
                   wire:model="quantity"
                   @class([
                       'rounded-md border-slate-300 bg-white text-center shadow-sm focus:border-orange-500 focus:ring-orange-500/30',
                       'h-7 w-10 text-xs' => $compact,
                       'h-8 w-11 text-sm' => ! $compact,
                   ])>
        </div>
    </div>
    @if (session('success'))
        <p @class(['mt-1.5 text-green-600', 'text-xs' => $compact, 'text-sm' => ! $compact])>{{ session('success') }}</p>
    @endif
</div>
