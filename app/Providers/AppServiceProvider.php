<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Older MySQL/MariaDB setups on shared hosting may reject utf8mb4 indexes at 255 chars.
        Schema::defaultStringLength(191);

        View::composer(['layouts.storefront', 'storefront.*'], function ($view): void {
            $view->with('storeName', Setting::get('store_name', config('app.name')));
        });
    }
}
