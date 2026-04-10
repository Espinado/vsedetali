<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (file_exists(base_path('vendor/filament/filament'))) {
    $providers[] = App\Providers\Filament\AdminPanelProvider::class;
    $providers[] = App\Providers\Filament\SellerPanelProvider::class;
}

return $providers;
