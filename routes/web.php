<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SellerStaffInviteController;
use App\Http\Controllers\StaffInviteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SitemapController;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Seo;
use Illuminate\Support\Facades\Route;

// GET в адресной строке не передаёт socket_id/channel_name — Laravel отдаёт 403. Реальный запрос — только POST из Echo.
if (config('app.debug')) {
    Route::get('/broadcasting/auth', function () {
        return response()->json([
            'message' => 'Служебный URL для POST из Laravel Echo (поля socket_id, channel_name). Открытие в браузере (GET) не поддерживается.',
        ], 400);
    });
}

if (app()->isLocal()) {
    Route::get('/__design/product-cards', function () {
        $demo = Product::query()
            ->with(['brand', 'category', 'images', 'stocks', 'crossNumbers', 'vehicles'])
            ->active()
            ->first();

        return view('storefront.design-product-cards', ['demo' => $demo]);
    })->name('design.product-cards');
}

// Auth (guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('throttle:12,1')->group(function () {
    Route::get('/staff/invite/{token}', [StaffInviteController::class, 'show'])->name('staff.invite.show');
    Route::post('/staff/invite/{token}', [StaffInviteController::class, 'update'])->name('staff.invite.update');
    Route::get('/seller/invite/{token}', [SellerStaffInviteController::class, 'show'])->name('seller-staff.invite.show');
    Route::post('/seller/invite/{token}', [SellerStaffInviteController::class, 'update'])->name('seller-staff.invite.update');
});

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    $sitemap = url('/sitemap.xml');
    $body = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /account/',
        'Disallow: /cart',
        'Disallow: /checkout',
        'Disallow: /login',
        'Disallow: /register',
        'Disallow: /staff/',
        '',
        'Sitemap: '.$sitemap,
    ]);

    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// Storefront
Route::get('/', function () {
    $banners = Banner::active()->get();
    $featuredProducts = Product::with(['category', 'brand', 'images', 'stocks'])->active()->latest()->take(8)->get();
    $metaDescription = trim((string) (Setting::get('site_meta_description') ?? ''));
    if ($metaDescription === '') {
        $metaDescription = 'Интернет-магазин автозапчастей. Каталог, доставка, удобная оплата.';
    }

    return view('storefront.home', [
        'banners' => $banners,
        'featuredProducts' => $featuredProducts,
        'metaDescription' => $metaDescription,
        'canonicalUrl' => url('/'),
    ]);
})->name('home');

Route::get('/catalog/{categorySlug?}', function () {
    return redirect()->route('home', request()->query(), 301);
})->name('catalog');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product.show');
Route::get('/cart', \App\Livewire\Storefront\CartPage::class)->name('cart');
Route::middleware(['auth', 'customer.not.blocked'])->group(function () {
    Route::get('/checkout', \App\Livewire\Storefront\CheckoutWizard::class)->name('checkout');
    Route::get('/checkout/payment/{order}', function (Order $order) {
        abort_if($order->user_id !== auth()->id(), 403);

        // Статус оплаты меняется на «оплачено» только когда администратор переведёт заказ во второй статус (подтверждён)
        return view('storefront.checkout-payment', ['order' => $order->load('paymentMethod', 'latestPayment')]);
    })->name('checkout.payment');
    Route::get('/checkout/success/{order}', function (Order $order) {
        abort_if($order->user_id !== auth()->id(), 403);

        return view('storefront.checkout-success', [
            'order' => $order->load(['status', 'paymentMethod', 'latestPayment']),
        ]);
    })->name('checkout.success');
});
Route::get('/page/{slug}', function (string $slug) {
    $page = Page::where('slug', $slug)->active()->firstOrFail();
    $metaDescription = Seo::metaDescription($page->meta_description, $page->body);

    return view('storefront.page', [
        'page' => $page,
        'metaDescription' => $metaDescription,
        'canonicalUrl' => route('page.show', ['slug' => $page->slug]),
    ]);
})->name('page.show');

// Account (auth required)
Route::middleware(['auth', 'customer.not.blocked'])->prefix('account')->name('account.')->group(function () {
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
        abort_if($order->user_id !== auth()->id(), 403);
        return view('account.order-show', ['order' => $order->load(['orderItems', 'orderAddresses', 'status', 'shippingMethod', 'paymentMethod', 'latestPayment', 'latestShipment.shippingMethod'])]);
    })->name('orders.show');
    Route::get('/profile', [\App\Http\Controllers\Account\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\Account\ProfileController::class, 'update'])->name('profile.update');
    Route::resource('addresses', \App\Http\Controllers\Account\AddressController::class)->except(['show']);
});
