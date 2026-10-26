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

    // aliens:b2b-import --source-categories: Aliens-Kategorie => unsere Kategorien (oder 'exclude').
    // Gilt nur für noch offene Zuordnungen; Entscheidungen 2026-10-26
    'sources' => [
        'Statikseile' => ['Statisch'],
        'Rettungstragen' => ['Tragen'],
        'Baumpflege' => ['Baumpflege-Zubehör'],
        'Bekleidung' => 'exclude',
        'Sicherungsgeräte' => ['Abseilgeräte'],
        'Schlingen & Lanyard' => ['Schlingen & Lanyard'],
        'Schlingen' => ['Schlingen & Lanyard'],
        'Hochseilgarten' => ['Schlingen & Lanyard'],
        'Positionierung' => ['Positionierung'],
        'Höhensicherungsgeräte' => ['Höhensicherungsgeräte'],
        'Helmzubehör' => ['Zubehör Schutzhelme'],
        'Klettersteigsets' => ['Bergsport & Klettern'],
        'Chalk' => ['Bergsport & Klettern'],
        'Chalkbags' => ['Bergsport & Klettern'],
        'Felshammer' => ['Bergsport & Klettern'],
        'Lawinensonden' => ['Bergsport & Klettern'],
        'Steigeisen' => ['Bergsport & Klettern'],
        'Sitzgurte' => ['Sitzgurt'],
        'Slackline' => ['Freizeit'],
        'Team Sets' => ['Freizeit'],
        'Werkzeughalter' => ['Werkzeugsicherung'],
        'Taschen & Bags' => ['Taschen & Rucksäcke'],
        'Steigklemmen' => ['Seilklemmen'],
        'Absturzsicherung' => ['Falldämpfer'],
        'Arbeitssicherheit' => ['PSAgA-Sets'],
        'Rettungsgeräte' => ['Dreibein'],
        'Anschlagpunkte' => ['Anschlageinrichtung'],
        'Haken & Ketten' => ['Anschlageinrichtung'],
    ],

    // Einzelne Produkte aus gemischten Aliens-Kategorien: Aliens-Produkt-ID => unsere Kategorien
    // (werden manuell zugeordnet); Entscheidungen 2026-10-26
    'products' => [
        2417 => ['Alukarabiner'],           // Karabiner ASC LINK
        2360 => ['Alukarabiner'],           // Karabiner Colt Screw 3Pack
        2252 => ['Alukarabiner'],           // Karabiner MINI FROG
        2428 => ['Stahlkarabiner'],         // Stahlkarabiner HMS STEEL CONNECTOR screw
        2429 => ['Stahlkarabiner'],         // Stahlkarabiner HMS STEEL CONNECTOR Trilock
        2423 => ['Seilklemmen'],            // Bruststeigklemme ASC LINK
        2311 => ['Zubehör Schutzhelme'],    // Ersatzkopfband Helm MOUSE
        1500 => ['Zubehör Schutzhelme'],    // Ersatzpolster Helm Mouse
        1683 => ['Zubehör Schutzhelme'],    // Mouse Plates
        2362 => ['Ersatzteil'],             // Betaclimbing Ersatzteilset
        1573 => ['Ersatzteil'],             // Gummiring für OKA
        1488 => ['Ersatzteil'],             // Schutzkappe Zip Cap
        1988 => ['Winde'],                  // Seilwinde RUP-503 (Protekt), Dreibein › Winde
        2434 => ['Rettung & Transport'],    // Kuh- und Pferdetransportgurt BERTA
        1564 => ['Rettung & Transport'],    // Protective Seat
        2283 => ['Rettung & Transport'],    // Rettungsset RESCUE SET
        1522 => ['Rettung & Transport'],    // Schwerlastgurt VIRGO
        1525 => ['Zubehör für Tragen'],     // LECCO Griffe
        1743 => ['Baumpflege-Zubehör'],     // ISC Rigging Rope Wrench
        2409 => ['Statisch'],               // Statikseil Patron Meterware
        1764 => ['Zubehör'],                // Back-Up Magnet
        2184 => ['Zubehör'],                // Hook Parking Loop
        2250 => ['Zubehör'],                // Retrieval Ball Abziehkirsche
        2262 => ['Zubehör'],                // Rope Tarp
        1629 => ['Zubehör'],                // Verbindungsschlinge
        2122 => ['Zubehör'],                // NFC-Chip für Meterware
        2424 => ['Zubehör'],                // Waschmittel Gear Wash
        1282 => ['Zubehör'],                // Höhlenleiter Speleo
        254 => ['Freizeit'],                // Skitrainer Easy Turn
    ],

    // Einträge dieser Kategorien sind keine Produkte (Stellenanzeigen, gedruckte Kataloge, News)
    'skip_categories' => [
        'Jobs | Stellenangebote',
        'Kataloge',
        'NEWS - Informationen',
    ],

];
