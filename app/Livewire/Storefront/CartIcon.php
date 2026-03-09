<?php

namespace App\Livewire\Storefront;

use App\Services\CartService;
use Livewire\Component;

class CartIcon extends Component
{
    protected $listeners = ['cart-updated' => 'refreshCount'];

    public function getCountProperty(): int
    {
        return app(CartService::class)->getItemsCount();
    }

    public function refreshCount(): void
    {
        unset($this->count);
    }

    public function render()
    {
        return view('livewire.storefront.cart-icon');
    }
}
