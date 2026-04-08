<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | RapidAPI: Auto Parts Catalog (TecDoc-подобные эндпоинты)
    | https://rapidapi.com/makingdatameaningful/api/auto-parts-catalog
    */
    'auto_parts_catalog' => [
        'key' => env('RAPIDAPI_AUTO_PARTS_KEY'),
        'host' => env('RAPIDAPI_AUTO_PARTS_HOST', 'auto-parts-catalog.p.rapidapi.com'),
        'base_url' => env('RAPIDAPI_AUTO_PARTS_BASE_URL', 'https://auto-parts-catalog.p.rapidapi.com'),
        /** Язык данных TecDoc: 16 = ru (см. GET /languages/list), иначе задайте в .env */
        'lang_id' => (int) env('RAPIDAPI_AUTO_PARTS_LANG_ID', 16),
        /** Регион/фильтр страны: из GET /countries/list или list-countries-by-lang-id */
        /** Пример: 63 (DE в выборках TecDoc); подберите id из /countries/list-countries-by-lang-id/{langId} */
        'country_filter_id' => (int) env('RAPIDAPI_AUTO_PARTS_COUNTRY_FILTER_ID', 63),
        /** Тип ТС: из GET /types/list-vehicles-type (часто 1 — легковые). Используется в т.ч. в selecting-a-list-of-cars-for-oem-part-number */
        'vehicle_type_id' => (int) env('RAPIDAPI_AUTO_PARTS_VEHICLE_TYPE_ID', 1),
        'timeout' => (int) env('RAPIDAPI_AUTO_PARTS_TIMEOUT', 30),
    ],

];
