<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (file_exists(base_path('vendor/filament/filament'))) {
    $providers[] = App\Providers\Filament\AdminPanelProvider::class;
}

return $providers;
