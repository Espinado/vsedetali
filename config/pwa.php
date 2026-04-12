<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Три отдельных PWA (витрина, админка, кабинет продавца)
    |--------------------------------------------------------------------------
    |
    | Определение по HTTP Host: совпадение с ADMIN_PANEL_DOMAIN / SELLER_PANEL_DOMAIN
    | или иначе — витрина (хост из APP_URL).
    | Иконки по умолчанию: public/pwa/icon-192.png, icon-512.png (можно заменить).
    |
    */

    'icons' => [
        '192' => '/pwa/icon-192.png',
        '512' => '/pwa/icon-512.png',
    ],

    'apps' => [
        'storefront' => [
            'manifest_id' => env('PWA_STOREFRONT_MANIFEST_ID', 'vsedetalki-storefront'),
            'name' => env('PWA_STOREFRONT_NAME', 'Все детали'),
            'short_name' => env('PWA_STOREFRONT_SHORT_NAME', 'Магазин'),
            'start_url' => env('PWA_STOREFRONT_START_URL', '/'),
            'scope' => env('PWA_STOREFRONT_SCOPE', '/'),
            'theme_color' => env('PWA_STOREFRONT_THEME', '#1c1917'),
            'background_color' => env('PWA_STOREFRONT_BG', '#fafaf9'),
        ],
        'admin' => [
            'manifest_id' => env('PWA_ADMIN_MANIFEST_ID', 'vsedetalki-admin'),
            'name' => env('PWA_ADMIN_NAME', 'Площадка — админ'),
            'short_name' => env('PWA_ADMIN_SHORT_NAME', 'Админ'),
            'start_url' => env('PWA_ADMIN_START_URL', '/'),
            'scope' => env('PWA_ADMIN_SCOPE', '/'),
            'theme_color' => env('PWA_ADMIN_THEME', '#ea580c'),
            'background_color' => env('PWA_ADMIN_BG', '#fafaf9'),
        ],
        'seller' => [
            'manifest_id' => env('PWA_SELLER_MANIFEST_ID', 'vsedetalki-seller'),
            'name' => env('PWA_SELLER_NAME', 'Кабинет продавца'),
            'short_name' => env('PWA_SELLER_SHORT_NAME', 'Продавец'),
            'start_url' => env('PWA_SELLER_START_URL', '/'),
            'scope' => env('PWA_SELLER_SCOPE', '/'),
            'theme_color' => env('PWA_SELLER_THEME', '#ea580c'),
            'background_color' => env('PWA_SELLER_BG', '#fafaf9'),
        ],
    ],

];
