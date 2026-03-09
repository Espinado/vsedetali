<?php

namespace App\Livewire\Storefront;

use App\Services\CartService;
use Livewire\Component;

class CartPage extends Component
{
    public function getCartProperty()
    {
        return app(CartService::class)->getOrCreateCart()
            ->load(['cartItems.product.category', 'cartItems.product.images']);
    }

    public function getItemsProperty()
    {
        return $this->cart->cartItems;
    }

    public function getSubtotalProperty(): float
    {
        return (float) $this->cart->cartItems->sum(fn ($item) => $item->quantity * $item->price);
    }

    public function updateQuantity(int $itemId, $quantity): void
    {
        $item = $this->cart->cartItems->firstWhere('id', $itemId);
        if (!$item) {
            return;
        }

        $qty = max(1, min(99, (int) $quantity));
        app(CartService::class)->updateQuantity($item, $qty);
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->cart->cartItems->firstWhere('id', $itemId);
        if (!$item) {
            return;
        }

        app(CartService::class)->removeItem($item);
        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.storefront.cart-page')
            ->layout('layouts.storefront', ['title' => 'Корзина']);
    }
}
