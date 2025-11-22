<?php

/**
 * Hersteller-Mapping: Aliens
 *
 * Dieses Mapping sorgt dafür, dass die Alien-Cams nicht nur in Felsrissen
 * funktionieren, sondern auch sauber in unserem Datenmodell landen. Die
 * berühmten Farben – wir bringen Ordnung rein. Versprochen.
 *
 * Pfad: config/import_mappings/aliens.php
 *
 * Verantwortlichkeiten:
 * - Abgleich der Alien-Farbcodes mit internen Werten
 * - Zuordnung der technischen Daten (Größe, Range, kN)
 * - Vereinheitlichung sehr kreativer Farbnamen
 *
 * Fun Fact:
 * Diese Cams heißen nicht ohne Grund „Aliens“. Manchmal wirken auch die Daten so. 😉
 *
 * @mapping-source   Aliens CSV
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForAliens
 */
return [
    // ...
];
