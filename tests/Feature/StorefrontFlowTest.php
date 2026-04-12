<?php

namespace Tests\Feature;

use App\Livewire\Storefront\CheckoutWizard;
use App\Models\Cart;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_auth_pages(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_checkout_redirects_guest_to_login(): void
    {
        $this->get(route('checkout'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cart_merges_into_user_cart_on_login(): void
    {
        $product = $this->createActiveProduct();
        $user = User::factory()->create([
            'email' => 'buyer@example.test',
            'password' => Hash::make('secret'),
        ]);

        // MakesHttpRequests не подставляет Set-Cookie из ответа в следующий запрос — без явной
        // передачи session-cookie каждый вызов получает новый session_id и merge не находит корзину.
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
        ]);
        $home = $this->get('/');
        $home->assertOk();
        $this->useSessionCookieFromResponse($home);

        $seed = $this->get('/_testing/guest-cart-seed/'.$product->getKey());
        $seed->assertOk();
        $this->useSessionCookieFromResponse($seed);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $login = $this->post('/login', [
            'email' => 'buyer@example.test',
            'password' => 'secret',
        ]);
        $this->withMiddleware();
        $login->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($user);

        $mergedCart = Cart::query()
            ->where('user_id', $user->id)
            ->whereHas('cartItems')
            ->first();

        $this->assertNotNull($mergedCart);
        $this->assertSame(2, (int) $mergedCart->cartItems()->sum('quantity'));
    }

    public function test_customer_can_open_home_product_cart_checkout_with_cart_item(): void
    {
        $product = $this->createActiveProduct();
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->addItem($product, 2);

        $this->get(route('catalog', ['categorySlug' => $product->category->slug]))
            ->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();
        $this->get(route('product.show', $product))->assertOk();
        $this->get(route('cart'))->assertOk();
        $this->get(route('checkout'))->assertOk();
    }

    public function test_checkout_wizard_places_order_and_clears_cart(): void
    {
        $product = $this->createActiveProduct();
        $user = User::factory()->create([
            'name' => 'Ivan Petrov',
            'email' => 'ivan@example.test',
        ]);
        OrderStatus::create([
            'name' => 'New',
            'slug' => 'new',
            'sort' => 1,
        ]);

        $this->actingAs($user);
        app(CartService::class)->addItem($product, 2);

        Livewire::test(CheckoutWizard::class)
            ->call('step1Next')
            ->set('delivery_name', 'Ivan Petrov')
            ->set('delivery_full_address', 'Lenina 10-1')
            ->set('delivery_city', 'Riga')
            ->set('delivery_country', 'LV')
            ->call('step2Next')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'customer_email' => 'ivan@example.test',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(200.0, (float) $order->total);
        $this->assertDatabaseCount('cart_items', 0);
    }

    private function useSessionCookieFromResponse(TestResponse $response): void
    {
        $name = (string) config('session.cookie');
        $cookie = $response->getCookie($name, false);
        $this->assertNotNull($cookie, 'В ответе нет session-cookie «'.$name.'» — нельзя продолжить сценарий гостя.');
        $this->withUnencryptedCookie($name, $cookie->getValue());
    }

    private function createActiveProduct(): Product
    {
        $category = Category::create([
            'name' => 'Test category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Test brand',
            'slug' => 'test-brand-storefront',
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'SKU-001',
            'name' => 'Test product',
            'slug' => 'test-product',
            'price' => 100,
            'is_active' => true,
            'type' => 'part',
        ]);
    }
}
