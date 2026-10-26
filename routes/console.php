<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Aliens-B2B-Bereich: Dateien nach der Aktualisierung bei Aliens abholen (nur Download).
// Läuft nur, wenn auf dem Server der Laravel-Scheduler per Cron aktiv ist.
Schedule::command('aliens:b2b-fetch produkte')->dailyAt('02:30')->withoutOverlapping();
// danach: Artikel, die Aliens nicht mehr führt, markieren und offline nehmen (bricht bei kaputtem Download ab)
Schedule::command('aliens:b2b-import storage/app/imports/aliens-b2b/produkte-aktuell.xlsx --mark-discontinued')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('aliens:b2b-fetch lagerbestand')->dailyAt('10:30')->withoutOverlapping();
