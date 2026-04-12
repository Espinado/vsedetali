<?php

namespace App\Providers;

use App\Broadcasting\GuestAwarePusherBroadcaster;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Console\Commands\DiagnosePanelsCommand;
use App\Observers\SellerObserver;
use App\Observers\UserObserver;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Reverb StartServer использует SIGINT/SIGTERM/SIGTSTP; без ext-pcntl на shared hosting константы не заданы.
        if (! extension_loaded('pcntl')) {
            foreach ([
                'SIGINT' => 2,
                'SIGTERM' => 15,
                'SIGTSTP' => 20,
            ] as $name => $value) {
                if (! defined($name)) {
                    define($name, $value);
                }
            }
        }

        // extend до любого boot() провайдера; каналы — в booted после Broadcast::routes() (bootstrap/app.php).
        $this->app->booting(function (): void {
            $manager = $this->app->make(BroadcastManager::class);

            foreach (['reverb', 'pusher'] as $driver) {
                $manager->extend($driver, function ($app, array $config) {
                    $pusher = $app->make(BroadcastManager::class)->pusher($config);

                    return new GuestAwarePusherBroadcaster($pusher, $config['jsonp'] ?? false);
                });
            }
        });

        $this->app->booted(function (): void {
            require base_path('routes/broadcast_channels.php');
        });

        // Кастомная пагинация Livewire (каталог и др.): views в resources/views/vendor/livewire должны иметь приоритет над пакетом.
        $this->app->booted(function (): void {
            if (is_dir(resource_path('views/vendor/livewire'))) {
                View::prependNamespace('livewire', [resource_path('views/vendor/livewire')]);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->commands([
            DiagnosePanelsCommand::class,
        ]);

        User::observe(UserObserver::class);
        Seller::observe(SellerObserver::class);

        Gate::before(function ($user, string $ability) {
            if ($user instanceof Staff && $user->hasRole('admin')) {
                return true;
            }

            return null;
        });

        // Older MySQL/MariaDB setups on shared hosting may reject utf8mb4 indexes at 255 chars.
        Schema::defaultStringLength(191);

        View::composer(['layouts.storefront', 'storefront.*'], function ($view): void {
            $view->with('storeName', Setting::storeDisplayName());
        });

        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        View::composer('layouts.storefront', function ($view): void {
            $data = $view->getData();
            if (array_key_exists('noindex', $data)) {
                return;
            }
            if (request()->routeIs([
                'login',
                'register',
                'cart',
                'checkout',
                'checkout.payment',
                'checkout.success',
                'account.*',
            ])) {
                $view->with('noindex', true);
            }
        });
    }
}
