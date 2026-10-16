<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fail2ban — zbieranie banów co minutę
Schedule::command('fail2ban:tick')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Fail2ban — retencja: usuwanie zamkniętych banów starszych niż 30 dni
Schedule::command('fail2ban:prune')
    ->daily()
    ->at('03:30')
    ->withoutOverlapping(30);

// Telescope — czyszczenie starych wpisów raz dziennie o 3:00
Schedule::command('telescope:prune --hours=48')
    ->daily()
    ->at('03:00');
