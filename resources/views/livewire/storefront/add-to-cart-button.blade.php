<div>
    <div class="flex flex-wrap items-end gap-4">
        <div class="flex items-center gap-2">
            <label for="qty-{{ $product->id }}" class="text-sm text-slate-600">Кол-во:</label>
            <input type="number"
                   id="qty-{{ $product->id }}"
                   min="1"
                   max="99"
                   wire:model="quantity"
                   class="w-20 rounded-lg border-slate-300 shadow-sm text-sm">
        </div>
        <button type="button"
                wire:click="addToCart"
                wire:loading.attr="disabled"
                class="px-6 py-3 bg-slate-800 text-white rounded-lg hover:bg-slate-700 disabled:opacity-50 text-sm font-medium">
            <span wire:loading.remove>В корзину</span>
            <span wire:loading>Добавление...</span>
        </button>
    </div>
    @if (session('success'))
        <p class="mt-2 text-sm text-green-600">{{ session('success') }}</p>
    @endif
</div>
