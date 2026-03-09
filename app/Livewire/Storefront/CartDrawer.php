<?php

namespace App\Livewire\Storefront;

use App\Models\CartItem;
use App\Services\CartService;
use Livewire\Component;

class CartDrawer extends Component
{
    public bool $open = false;

    protected $listeners = [
        'open-cart-drawer' => 'openDrawer',
        'close-cart-drawer' => 'closeDrawer',
        'cart-updated' => 'refreshCart',
    ];

    public function getCartProperty()
    {
        return app(CartService::class)->getOrCreateCart()
            ->load(['cartItems.product.images']);
    }

    public function getItemsProperty()
    {
        return $this->cart->cartItems;
    }

    public function getSubtotalProperty(): float
    {
        return (float) $this->items->sum(fn (CartItem $item) => $item->total);
    }

    public function openDrawer(): void
    {
        $this->refreshCart();
        $this->open = true;
    }

    public function closeDrawer(): void
    {
        $this->open = false;
    }

    public function refreshCart(): void
    {
        unset($this->cart, $this->items, $this->subtotal);
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        $item = $this->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $qty = max(1, min(99, $quantity));
        app(CartService::class)->updateQuantity($item, $qty);
        $this->refreshCart();
        $this->dispatch('cart-updated');
        $this->open = true;
    }

    public function removeItem(int $itemId): void
    {
        $item = $this->items->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        app(CartService::class)->removeItem($item);
        $this->refreshCart();
        $this->dispatch('cart-updated');
        $this->open = true;
    }

    public function render()
    {
        return view('livewire.storefront.cart-drawer');
    }
}
