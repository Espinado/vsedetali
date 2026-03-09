<?php

namespace App\Livewire\Storefront;

use App\Models\Product;
use App\Services\CartService;
use Livewire\Component;

class AddToCartButton extends Component
{
    public Product $product;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function addToCart(): void
    {
        $this->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        app(CartService::class)->addItem($this->product, $this->quantity);

        $this->dispatch('cart-updated');
        $this->dispatch('open-cart-drawer');
        session()->flash('success', 'Товар добавлен в корзину.');
        $this->quantity = 1;
    }

    public function render()
    {
        return view('livewire.storefront.add-to-cart-button');
    }
}
