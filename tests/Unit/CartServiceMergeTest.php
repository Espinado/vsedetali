<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_moves_guest_cart_to_user_by_session_id(): void
    {
        $category = Category::create([
            'name' => 'Cat',
            'slug' => 'cat',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Br',
            'slug' => 'br-cart-merge',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'SKU-M',
            'name' => 'P',
            'slug' => 'p',
            'price' => 50,
            'is_active' => true,
            'type' => 'part',
        ]);
        $user = User::factory()->create();

        $guestCart = Cart::create([
            'session_id' => 'explicit-guest-session-id',
            'user_id' => null,
        ]);
        CartItem::create([
            'cart_id' => $guestCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => $product->price,
        ]);

        app(CartService::class)->mergeGuestCartIntoUserCart($user, 'explicit-guest-session-id');

        $merged = Cart::query()
            ->where('user_id', $user->id)
            ->whereHas('cartItems')
            ->first();

        $this->assertNotNull($merged);
        $this->assertSame(2, (int) $merged->cartItems()->sum('quantity'));
        $this->assertNull($merged->session_id);
    }
}
