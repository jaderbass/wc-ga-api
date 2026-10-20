<?php

/*
|--------------------------------------------------------------------------
| Aliens-B2B-Produktdaten (aliens:b2b-import)
|--------------------------------------------------------------------------
*/

return [

    // Herstellername in der Aliens-Datei (ohne Groß-/Kleinschreibung) => unser Hersteller
    // Entscheidungen 2026-10-20
    'manufacturers' => [
        'fujfuj' => 'FUJFUJ',
        'panther-spikes' => 'PANTHER SPIKES',
        'chalk' => 'Aliens',
    ],

    // Einträge dieser Kategorien sind keine Produkte (z. B. Stellenanzeigen)
    'skip_categories' => [
        'Jobs | Stellenangebote',
    ],

];
