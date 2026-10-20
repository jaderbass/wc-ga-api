<?php

/*
|--------------------------------------------------------------------------
| Aliens-B2B-Bereich (aliens:b2b-fetch, aliens:b2b-import)
|--------------------------------------------------------------------------
*/

return [

    // Download-Schlüssel aus dem Aliens-B2B-Bereich – nur in der .env, nie im Code (Repo ist öffentlich)
    'key' => env('ALIENS_B2B_KEY'),

    'base_url' => env('ALIENS_B2B_BASE_URL', 'https://b2b.aliens-outdoor.com/wp-content/listen/'),

    // Dateien, die aliens:b2b-fetch abholt (Aliens aktualisiert Produkte 0:00–1:30, Lager um 10:00)
    'files' => [
        'produkte' => 'Aliens-Produkte.xlsx',
        'lagerbestand' => 'Lagerbestandsliste.xlsx',
    ],

    // Ablage, relativ zu storage/app
    'directory' => 'imports/aliens-b2b',

    // so viele Tage alte Downloads behalten
    'keep_days' => 14,

    // Herstellername in der Aliens-Datei (ohne Groß-/Kleinschreibung) => unser Hersteller
    // Entscheidungen 2026-10-20
    'manufacturers' => [
        'fujfuj' => 'FUJFUJ',
        'panther-spikes' => 'PANTHER SPIKES',
        'chalk' => 'Aliens',
    ],

    // Einträge dieser Kategorien sind keine Produkte (Stellenanzeigen, gedruckte Kataloge, News)
    'skip_categories' => [
        'Jobs | Stellenangebote',
        'Kataloge',
        'NEWS - Informationen',
    ],

];
