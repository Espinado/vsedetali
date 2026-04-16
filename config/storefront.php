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

    /*
    |--------------------------------------------------------------------------
    | Перебор годов в селекте «Подбор запчасти по автомобилю» (главная)
    |--------------------------------------------------------------------------
    |
    | В селекте остаются только годы, для которых есть хотя бы один активный товар
    | с учётом pivot compat_year_* и year_from/year_to ТС. Окно перебора ограничиваем
    | этими ключами (при пустых годах в справочнике — fallback_from / fallback_to).
    |
    */
    'vehicle_year_search_from' => is_numeric(env('STOREFRONT_VEHICLE_YEAR_SEARCH_FROM'))
        ? (int) env('STOREFRONT_VEHICLE_YEAR_SEARCH_FROM')
        : null,

    /** null — как vehicle_year_fallback_to или текущий год */
    'vehicle_year_search_to' => is_numeric(env('STOREFRONT_VEHICLE_YEAR_SEARCH_TO'))
        ? (int) env('STOREFRONT_VEHICLE_YEAR_SEARCH_TO')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Модерация конфликтов «название товара ↔ категория из каталога»
    |--------------------------------------------------------------------------
    |
    | При обогащении из TecDoc/RapidAPI, если эвристика видит явный разнобой
    | (например, оптика в названии и тормоза в категории), category_id не меняем,
    | а строка дописывается в TSV (UTF-8) для ручной проверки.
    |
    */
    'category_conflict_moderation_path' => env('STOREFRONT_CATEGORY_CONFLICT_MODERATION_PATH'),

];
