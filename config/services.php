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
        /** Макс. позиций в блоке VIN → категория → детали (главная). */
        'vin_flow_article_list_limit' => max(1, min(100, (int) env('RAPIDAPI_AUTO_PARTS_VIN_ARTICLE_LIMIT', 48))),
        'timeout' => (int) env('RAPIDAPI_AUTO_PARTS_TIMEOUT', 30),
        /**
         * Доп. GET-пути к карточке артикула (через «|»), плейсхолдеры {articleId} и {langId}.
         * См. плейграунд RapidAPI — если «богатый» JSON с articleInfo/compatibleCars на другом маршруте.
         */
        'article_detail_paths_extra' => env('RAPIDAPI_AUTO_PARTS_ARTICLE_DETAIL_PATHS_EXTRA', ''),
        /** true: при пустом списке категорий (VIN flow) писать в лог сводку HTTP (статус, ключи JSON, фрагмент тела). */
        'log_category_on_empty' => filter_var(env('RAPIDAPI_AUTO_PARTS_LOG_CATEGORY_ON_EMPTY', false), FILTER_VALIDATE_BOOL),
    ],

    /*
    | RapidAPI: TecDoc Catalog (ronhartman) — альтернативный источник «чистых» категорий TecDoc.
    | https://rapidapi.com/ronhartman/api/tecdoc-catalog
    | Используется command catalog:tecdoc-lookup-by-oem для проверки качества категорий.
    */
    'tecdoc_catalog' => [
        'key' => env('RAPIDAPI_TECDOC_CATALOG_KEY'),
        'host' => env('RAPIDAPI_TECDOC_CATALOG_HOST', 'tecdoc-catalog.p.rapidapi.com'),
        'base_url' => env('RAPIDAPI_TECDOC_CATALOG_BASE_URL', 'https://tecdoc-catalog.p.rapidapi.com'),
        'lang_id' => (int) env('RAPIDAPI_TECDOC_CATALOG_LANG_ID', 16),
        'country_filter_id' => (int) env('RAPIDAPI_TECDOC_CATALOG_COUNTRY_FILTER_ID', 63),
        'vehicle_type_id' => (int) env('RAPIDAPI_TECDOC_CATALOG_VEHICLE_TYPE_ID', 1),
        'timeout' => (int) env('RAPIDAPI_TECDOC_CATALOG_TIMEOUT', 30),
    ],

    /*
    | NHTSA VIN Decoder API (бесплатный публичный декодер VIN)
    | https://vpic.nhtsa.dot.gov/api/
    */
    'vin_decoder' => [
        'base_url' => env('VIN_DECODER_BASE_URL', 'https://vpic.nhtsa.dot.gov/api'),
        'timeout' => (int) env('VIN_DECODER_TIMEOUT', 20),
        // Vincario fallback (good EU VIN coverage).
        'vincario_base_url' => env('VIN_DECODER_VINCARIO_BASE_URL', 'https://api.vincario.com/3.2'),
        'vincario_api_key' => env('VIN_DECODER_VINCARIO_API_KEY'),
        'vincario_secret_key' => env('VIN_DECODER_VINCARIO_SECRET_KEY'),
        'vincario_timeout' => (int) env('VIN_DECODER_VINCARIO_TIMEOUT', 20),
        // RapidAPI VIN fallback (higher priority than other secondary providers).
        'rapidapi_key' => env('RAPIDAPI_VIN_DECODER_KEY', env('RAPIDAPI_AUTO_PARTS_KEY')),
        'rapidapi_host' => env('RAPIDAPI_VIN_DECODER_HOST', 'vin-decoder-api1.p.rapidapi.com'),
        'rapidapi_base_url' => env('RAPIDAPI_VIN_DECODER_BASE_URL', 'https://vin-decoder-api1.p.rapidapi.com'),
        'rapidapi_path' => env('RAPIDAPI_VIN_DECODER_PATH', '/decode'),
        'rapidapi_timeout' => (int) env('RAPIDAPI_VIN_DECODER_TIMEOUT', 20),
        // Optional secondary provider (example: api-ninjas vinlookup).
        'secondary_provider' => env('VIN_DECODER_SECONDARY_PROVIDER', 'api_ninjas'),
        'secondary_base_url' => env('VIN_DECODER_SECONDARY_BASE_URL', 'https://api.api-ninjas.com/v1'),
        'secondary_key' => env('VIN_DECODER_SECONDARY_KEY'),
        'secondary_timeout' => (int) env('VIN_DECODER_SECONDARY_TIMEOUT', 20),
    ],

];
