<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retencja banów (dni)
    |--------------------------------------------------------------------------
    |
    | Jak długo trzymamy zamknięte bany w tabeli `fail2ban_bans`. Starsze wpisy
    | usuwa komenda `fail2ban:prune` (scheduler: codziennie 03:30).
    |
    | Ta wartość jest jedynym źródłem prawdy: komenda bierze z niej domyślne
    | `--days`, a panel buduje z niej etykiety („Razy (N dni)”), więc po zmianie
    | retencji opisy w UI nadal odpowiadają realnemu oknu danych.
    |
    */

    'retention_days' => (int) env('FAIL2BAN_RETENTION_DAYS', 30),

];
