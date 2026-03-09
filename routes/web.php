<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Livewire\Storefront\ProductGrid;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// Auth (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Storefront
Route::get('/', function () {
    $banners = Banner::active()->get();
    $featuredProducts = Product::with(['category', 'brand', 'images', 'stocks'])->active()->latest()->take(8)->get();
    return view('storefront.home', ['banners' => $banners, 'featuredProducts' => $featuredProducts]);
})->name('home');
Route::get('/catalog/{categorySlug?}', ProductGrid::class)->name('catalog');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/cart', \App\Livewire\Storefront\CartPage::class)->name('cart');
Route::get('/checkout', \App\Livewire\Storefront\CheckoutWizard::class)->name('checkout')->middleware('auth');
Route::get('/checkout/payment/{order}', function (Order $order) {
    abort_if($order->user_id !== auth()->id() && ! auth()->user()?->is_admin, 403);

    // Статус оплаты меняется на «оплачено» только когда администратор переведёт заказ во второй статус (подтверждён)
    return view('storefront.checkout-payment', ['order' => $order->load('paymentMethod', 'latestPayment')]);
})->name('checkout.payment')->middleware('auth');
Route::get('/checkout/success/{order}', function (Order $order) {
    abort_if($order->user_id !== auth()->id() && ! auth()->user()?->is_admin, 403);

    return view('storefront.checkout-success', [
        'order' => $order->load(['status', 'paymentMethod', 'latestPayment']),
    ]);
})->name('checkout.success')->middleware('auth');
Route::get('/page/{slug}', function (string $slug) {
    $page = Page::where('slug', $slug)->active()->firstOrFail();
    return view('storefront.page', ['page' => $page]);
})->name('page.show');

// Account (auth required)
Route::middleware(['auth'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', function () {
        $user = auth()->user();
        $recentOrders = $user->orders()->with(['status', 'latestPayment'])->latest()->take(5)->get();
        return view('account.dashboard', ['recentOrders' => $recentOrders]);
    })->name('dashboard');
    Route::get('/orders', function () {
        $orders = auth()->user()->orders()->with(['status', 'latestPayment', 'latestShipment'])->latest()->paginate(10);
        return view('account.orders', ['orders' => $orders]);
    })->name('orders.index');
    Route::get('/orders/{order}', function (App\Models\Order $order) {
        abort_if($order->user_id !== auth()->id() && ! auth()->user()?->is_admin, 403);
        return view('account.order-show', ['order' => $order->load(['orderItems', 'orderAddresses', 'status', 'shippingMethod', 'paymentMethod', 'latestPayment', 'latestShipment.shippingMethod'])]);
    })->name('orders.show');
    Route::get('/profile', [\App\Http\Controllers\Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\Account\ProfileController::class, 'update'])->name('profile.update');
    Route::resource('addresses', \App\Http\Controllers\Account\AddressController::class)->except(['show']);
});
