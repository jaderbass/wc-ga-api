<?php

/**
 * Hersteller-Mapping: Kask
 *
 * Hier landen die Kask-Helm-Daten, bevor sie uns den Kopf verdrehen.
 * Die originalen Datenquellen sind so gut strukturiert wie ein Helmriemen,
 * den jemand bei Windstärke 8 angelegt hat – wir machen sie glatt.
 *
 * Pfad: config/import_mappings/kask.php
 *
 * Verantwortlichkeiten:
 * - Mapping von Helmgrößen und Farbcodes
 * - Vereinheitlichung der Modellnamen
 * - Auswertung sicherheitsrelevanter Angaben
 *
 * Fun Fact:
 * "One size fits all" gilt in der Datenwelt nicht – darum gibt’s dieses Mapping.
 *
 * @mapping-source   Kask CSV/XML
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForKask
 */
return [
    // ...
];
