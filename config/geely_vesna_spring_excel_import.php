<?php

/**
 * Импорт XLSX «ДЖИЛИ ВЕСНА / БАМБУК»: жёлтая строка + столбец B = категория; A = марка модель; C = артикул; D = остаток.
 */
return [

    'sheet_index' => (int) env('GEELY_VESNA_EXCEL_SHEET_INDEX', 0),

    /** Первая строка данных (1 = первая строка листа). */
    'first_data_row' => (int) env('GEELY_VESNA_EXCEL_FIRST_ROW', 1),

    /**
     * Родительская категория витрины для подкатегорий из столбца B.
     */
    'parent_category' => [
        'name' => 'Geely Весна (импорт)',
        'slug' => 'import-geely-vesna-spring',
    ],
];
