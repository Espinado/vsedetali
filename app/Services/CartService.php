<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CartService
{
    /**
     * Переносит корзину гостя (по session_id) в корзину пользователя.
     * Вызывать до session()->regenerate() при входе/регистрации, иначе id сессии меняется и товары «теряются».
     */
    public function mergeGuestCartIntoUserCart(User $user, string $guestSessionId): void
    {
        if ($guestSessionId === '') {
            return;
        }

        $guestCart = Cart::query()
            ->where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->latest('id')
            ->first();

        if (! $guestCart || $guestCart->cartItems()->doesntExist()) {
            return;
        }

        $guestCart->load('cartItems');

        $userCart = Cart::query()->where('user_id', $user->id)->latest('id')->first();

        if (! $userCart) {
            $guestCart->update(['user_id' => $user->id, 'session_id' => null]);

            return;
        }

        foreach ($guestCart->cartItems as $item) {
            $existing = CartItem::query()
                ->where('cart_id', $userCart->id)
                ->where('product_id', $item->product_id)
                ->when($item->seller_id !== null, fn ($q) => $q->where('seller_id', $item->seller_id))
                ->when($item->seller_id === null, fn ($q) => $q->whereNull('seller_id'))
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $item->quantity]);
                $item->delete();
            } else {
                $item->update(['cart_id' => $userCart->id]);
            }
        }

        $guestCart->delete();
    }

    public function getOrCreateCart(): Cart
    {
        if (Auth::check()) {
            $cart = Cart::where('user_id', Auth::id())->latest()->first();
            if ($cart) {
                return $cart;
            }
            return Cart::create(['user_id' => Auth::id()]);
        }

        $sessionId = session()->getId();
        $cart = Cart::where('session_id', $sessionId)->latest()->first();
        if ($cart) {
            return $cart;
        }

        return Cart::create(['session_id' => $sessionId]);
    }

    public function addItem(Product $product, int $quantity = 1, ?int $sellerId = null): CartItem
    {
        $cart = $this->getOrCreateCart();

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
            ->when($sellerId === null, fn ($q) => $q->whereNull('seller_id'))
            ->first();

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + $quantity]);
            return $existing->fresh();
        }

        return CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
            'seller_id' => $sellerId,
        ]);
    }

    public function updateQuantity(CartItem $item, int $quantity): bool
    {
        if ($quantity < 1) {
            return $item->delete();
        }
        $item->update(['quantity' => $quantity]);
        return true;
    }

    public function removeItem(CartItem $item): bool
    {
        return $item->delete();
    }

    public function getItemsCount(): int
    {
        $cart = $this->getOrCreateCart();
        return $cart->cartItems()->sum('quantity');
    }
}
