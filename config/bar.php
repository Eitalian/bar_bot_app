<?php

return [
    'id'   => 1,
    'name' => 'Полторушка',

    'working_hours' => [
        'start' => '12:00',
        'end'   => '06:00', // через полночь
    ],

    /*
     * Запрет открытия сессии за N минут до конца окна.
     * Не даёт стартовать сессию, которая тут же закроется.
     */
    'open_cutoff_minutes' => 30,

    'search' => [
        'per_page' => (int) env('BAR_SEARCH_PER_PAGE', 15),
    ],
];
