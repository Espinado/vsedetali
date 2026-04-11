<div>
    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
        <div class="flex items-center gap-2">
            <label for="qty-{{ $product->id }}" class="shrink-0 text-sm text-slate-600">Кол-во:</label>
            <input type="number"
                   id="qty-{{ $product->id }}"
                   min="1"
                   max="99"
                   wire:model="quantity"
                   class="h-11 w-24 rounded-lg border-slate-300 text-center text-sm shadow-sm">
        </div>
        <button type="button"
                wire:click="addToCart"
                wire:loading.attr="disabled"
                class="btn-store-cta w-full sm:w-auto">
            <span wire:loading.remove>В корзину</span>
            <span wire:loading>Добавление...</span>
        </button>
    </div>
    @if (session('success'))
        <p class="mt-2 text-sm text-green-600">{{ session('success') }}</p>
    @endif
</div>
