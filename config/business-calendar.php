<?php

use Illuminate\Support\Carbon;

return [
    /* ISO weekday numbers (Monday = 1, Sunday = 7). */
    'closed_weekdays' => [Carbon::FRIDAY],

    /* Developers may add entries such as '2026-12-16' => 'Victory Day'. */
    'holidays' => [],

    'test_drive_slots' => [
        ['10:00', '10:30'],
        ['11:00', '11:30'],
        ['12:00', '12:30'],
        ['15:00', '15:30'],
        ['16:00', '16:30'],
    ],
];
