<?php

/*
|--------------------------------------------------------------------------
| Zugang zum Backend
|--------------------------------------------------------------------------
|
| Bewusst im Code und nicht in der .env: Änderungen laufen nur über einen
| Pull Request.
|
*/

return [

    // Benutzer mit einer dieser Rollen dürfen ins Backend.
    'panel_roles' => ['Admin'],

    // Besondere Konten: kommen immer ins Backend (auch ohne Rolle) und können
    // nur vom Konto selbst geändert oder gelöscht werden.
    // Eintrag: hash('sha256', E-Mail in Kleinbuchstaben).
    'protected_email_hashes' => [
        '867cf9630452f0ccbc4a0f5d691b941f278e0fff86cb5d2cb69e590217df0ffd',
    ],

    // Betriebsschalter: Existiert die Datei, ist die Datenbank abgeschaltet.
    'lock_file' => storage_path('app/system-lock.json'),

];
