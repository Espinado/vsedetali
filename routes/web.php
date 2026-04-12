<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FilamentSessionLoginController;
use App\Http\Middleware\RedirectPanelSubdomainsFromStorefront;
use App\Http\Controllers\PwaManifestController;
use App\Http\Controllers\PwaServiceWorkerController;
use App\Http\Controllers\SellerStaffInviteController;
use App\Http\Controllers\StaffInviteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SitemapController;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Support\Seo;
use Illuminate\Support\Facades\Log;
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

$appUrlRaw = trim((string) config('app.url'));
$appUrlNormalized = (str_starts_with($appUrlRaw, 'http://') || str_starts_with($appUrlRaw, 'https://'))
    ? $appUrlRaw
    : 'https://'.$appUrlRaw;
$parsedStorefrontHost = parse_url($appUrlNormalized, PHP_URL_HOST);
$parsedStorefrontHost = is_string($parsedStorefrontHost) ? $parsedStorefrontHost : '';
$storefrontHost = trim((string) config('panels.storefront_domain')) ?: $parsedStorefrontHost;
$panelsUseDedicatedHosts = filled(config('panels.admin.domain')) || filled(config('panels.seller.domain'));

if ($panelsUseDedicatedHosts && $storefrontHost === '') {
    Log::warning('Задайте APP_URL=https://… или STOREFRONT_DOMAIN=vsedetalki.ru, иначе витрину нельзя привязать к одному хосту.');
}

$inviteThrottle = ['throttle:12,1'];

$registerStaffInviteRoutes = function () {
    Route::get('/staff/invite/{token}', [StaffInviteController::class, 'show'])->name('staff.invite.show');
    Route::post('/staff/invite/{token}', [StaffInviteController::class, 'update'])->name('staff.invite.update');
};

$registerSellerInviteRoutes = function () {
    Route::get('/seller/invite/{token}', [SellerStaffInviteController::class, 'show'])->name('seller-staff.invite.show');
    Route::post('/seller/invite/{token}', [SellerStaffInviteController::class, 'update'])->name('seller-staff.invite.update');
};

$adminPanelDomain = config('panels.admin.domain');
$sellerPanelDomain = config('panels.seller.domain');

if (filled($adminPanelDomain)) {
    Route::domain($adminPanelDomain)->group(function () use ($inviteThrottle, $registerStaffInviteRoutes) {
        Route::middleware($inviteThrottle)->group($registerStaffInviteRoutes);
        // POST /login: fallback, если форма Filament ушла не в Livewire (иначе 405 на поддомене панели).
        Route::post('/login', [FilamentSessionLoginController::class, 'admin'])->middleware('throttle:12,1');
    });
} else {
    Route::middleware($inviteThrottle)->group($registerStaffInviteRoutes);
}

if (filled($sellerPanelDomain)) {
    Route::domain($sellerPanelDomain)->group(function () use ($inviteThrottle, $registerSellerInviteRoutes) {
        Route::middleware($inviteThrottle)->group($registerSellerInviteRoutes);
        Route::post('/login', [FilamentSessionLoginController::class, 'seller'])->middleware('throttle:12,1');
    });
} else {
    Route::middleware($inviteThrottle)->group($registerSellerInviteRoutes);
}

$registerStorefrontRoutes = function () use ($panelsUseDedicatedHosts): void {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login']);
        Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register']);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/robots.txt', function () use ($panelsUseDedicatedHosts) {
        $sitemap = url('/sitemap.xml');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /account/',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /staff/',
        ];
        if (! $panelsUseDedicatedHosts) {
            $lines[] = 'Disallow: /admin';
            $lines[] = 'Disallow: /seller';
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.$sitemap;

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    })->name('robots');

    Route::get('/', function () {
        $vehicleId = (int) request()->query('vehicleId', 0);
        if ($vehicleId > 0) {
            $vehicle = Vehicle::query()->find($vehicleId);
            if ($vehicle !== null) {
                return redirect()->route('vehicle.parts', $vehicle, 302);
            }
        }

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

    Route::get('/vehicle/{vehicle}', \App\Livewire\Storefront\ProductGrid::class)
        ->name('vehicle.parts');

    /** Подбор по марке/модели/году и id ТС (query); канонический URL — vehicle.parts */
    Route::get('/parts/by-car', \App\Livewire\Storefront\ProductGrid::class)
        ->name('vehicle.by_car');

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

    if (app()->environment('testing')) {
        // Только phpunit: тот же домен/сессия, что и у витрины (см. StorefrontFlowTest — merge при логине).
        Route::get('/_testing/guest-cart-seed/{product}', function (Product $product) {
            app(\App\Services\CartService::class)->addItem($product, 2);

            return response('ok', 200);
        })->name('testing.guest-cart-seed');
    }
};

if ($panelsUseDedicatedHosts && $storefrontHost !== '') {
    Route::domain($storefrontHost)->group($registerStorefrontRoutes);
} elseif (! $panelsUseDedicatedHosts) {
    $registerStorefrontRoutes();
} else {
    Log::error('Витрина: не удалось взять хост из APP_URL — проверьте APP_URL=https://vsedetalki.ru или STOREFRONT_DOMAIN; php artisan config:clear. Временно панели редиректятся с / на /login.');
    Route::middleware([RedirectPanelSubdomainsFromStorefront::class])->group($registerStorefrontRoutes);
}

Route::get('/manifest.webmanifest', PwaManifestController::class)->name('pwa.manifest');
Route::get('/sw.js', PwaServiceWorkerController::class)->name('pwa.sw');
