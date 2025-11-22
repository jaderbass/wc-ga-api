<?php

/**
 * Hersteller-Mapping: Skylotec
 *
 * Dieses Mapping wandelt Skylotec-Daten aus einer Art Sicherheitsdatenblatt,
 * Produktkatalog und halbem Roman in eine praktische Struktur um.
 *
 * Pfad: config/import_mappings/skylotec.php
 *
 * Verantwortlichkeiten:
 * - Mapping von PSA-Angaben & Zertifizierungen
 * - Normierte Größen, Farben und Seriennamen
 * - Variantenbildung (z. B. Längen, Breiten, Ausführungen)
 *
 * Fun Fact:
 * Skylotec liefert exzellente Sicherheitsausrüstung – und Daten, die man retten muss.
 *
 * @mapping-source   Skylotec CSV/XML
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForSkylotec
 */
return [
    // ...
];
