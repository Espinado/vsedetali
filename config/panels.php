<?php

use App\Support\PanelDomain;

return [

    /*
    |--------------------------------------------------------------------------
    | Filament: отдельные хосты для панелей
    |--------------------------------------------------------------------------
    |
    | Если задан домен — панель открывается с корня этого хоста (без /admin, /seller).
    | Если пусто — используется path (локальные тесты или временный fallback).
    | SESSION_DOMAIN оставьте null: cookie привязаны к конкретному хосту, сессии не общие.
    |
    */

    'admin' => [
        'domain' => PanelDomain::normalizeEnv('ADMIN_PANEL_DOMAIN'),
        'path' => env('ADMIN_PANEL_PATH', 'admin'),
    ],

    'seller' => [
        'domain' => PanelDomain::normalizeEnv('SELLER_PANEL_DOMAIN'),
        'path' => env('SELLER_PANEL_PATH', 'seller'),
    ],

    /*
    | Явный хост витрины (если из APP_URL хост не получается). Должен совпадать с тем, что в браузере у покупателей.
    */
    'storefront_domain' => PanelDomain::normalizeEnv('STOREFRONT_DOMAIN'),

    'session_cookies' => [
        'admin' => env('ADMIN_SESSION_COOKIE', 'vsedetali-admin-session'),
        'seller' => env('SELLER_SESSION_COOKIE', 'vsedetali-seller-session'),
    ],

];
