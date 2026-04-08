<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Категории, скрытые в витрине (каталог)
    |--------------------------------------------------------------------------
    |
    | Служебные категории импорта не показываем в сайдбаре; URL с их slug
    | ведёт на общий каталог без фильтра по категории.
    |
    */
    'hidden_category_slug_prefix' => 'import-',

    /*
    |--------------------------------------------------------------------------
    | Год в фильтре «Подбор по авто»
    |--------------------------------------------------------------------------
    |
    | Если у всех Vehicle для пары марка/модель годы в БД пустые, список годов
    | в селекте был бы пустым. Тогда показываем диапазон «от — до» (фильтр по году
    | всё равно совместим с year_from/year_to = null).
    |
    */
    'vehicle_year_fallback_when_empty' => env('STOREFRONT_VEHICLE_YEAR_FALLBACK_WHEN_EMPTY', true),

    'vehicle_year_fallback_from' => (int) env('STOREFRONT_VEHICLE_YEAR_FALLBACK_FROM', 1990),

    /** null — текущий календарный год */
    'vehicle_year_fallback_to' => is_numeric(env('STOREFRONT_VEHICLE_YEAR_FALLBACK_TO'))
        ? (int) env('STOREFRONT_VEHICLE_YEAR_FALLBACK_TO')
        : null,

];
