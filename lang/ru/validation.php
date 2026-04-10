<?php

return [
    'required' => 'Заполните поле «:attribute».',
    'array' => 'Поле «:attribute» должно быть списком.',
    'min' => [
        'array' => 'Выберите хотя бы :min значений в поле «:attribute».',
        'numeric' => 'Значение «:attribute» не может быть меньше :min.',
        'string' => 'Поле «:attribute» должно содержать не меньше :min символов.',
    ],
    'max' => [
        'string' => 'Поле «:attribute» не может быть длиннее :max символов.',
        'numeric' => 'Значение «:attribute» не может быть больше :max.',
    ],
    'numeric' => 'Поле «:attribute» должно быть числом.',
    'integer' => 'Поле «:attribute» должно быть целым числом.',
    'email' => 'Укажите корректный email в поле «:attribute».',

    'attributes' => [
        'vehicle_compatibilities' => 'совместимость с авто',
        'vehicle_compatibilities.*.vehicle_make' => 'марка (совместимость)',
        'vehicle_compatibilities.*.vehicle_model' => 'модель (совместимость)',
        'vehicle_compatibilities.*.compatibility_years' => 'годы выпуска (совместимость)',
        'vehicle_make' => 'марка',
        'vehicle_model' => 'модель',
        'compatibility_years' => 'годы выпуска',
        'listing_name' => 'название',
        'listing_images' => 'фотографии',
        'price' => 'цена',
        'cost_price' => 'себестоимость',
        'quantity' => 'количество',
        'oem_code' => 'OEM-код',
        'article' => 'артикул',
        'shipping_days' => 'срок отгрузки',
    ],
];
