<?php

/**
 * Hersteller-Mapping: Petzl
 *
 * Dieses Mapping zähmt die Petzl-Daten, die manchmal mehr Dokumentation
 * enthalten als ein kompletter Kletterkurs. Am Ende bleibt das übrig,
 * was wir wirklich brauchen.
 *
 * Pfad: config/import_mappings/petzl.php
 *
 * Verantwortlichkeiten:
 * - Mapping von Petzl-Attributen wie Farbe, Größe, Serien
 * - Normalisierung von Petzl-Bezeichnungen
 * - Produktbündelung nach Varianten
 *
 * Fun Fact:
 * Wenn Petzl eine „Sonderversion“ meint, meinen sie meistens „andere Farbe“.
 *
 * @mapping-source   Petzl CSV/XML/API
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForPetzl
 */
return [
  // Feld, nach dem die Zeilen zu einem Hauptprodukt gruppiert werden.
  'group_by' => 'Product name',

  // Eindeutige Referenz/SKU für eine einzelne Variante.
  'reference' => 'Reference',

  // Mapping für Felder des Hauptprodukts (wird aus der ersten Zeile der Gruppe genommen).
  // Alle Felder, die im Hauptprodukt landen sollen, müssen hier definiert werden.
  'product' => [
    'product_name'   => 'Product name',
    'product_number' => 'Reference',
    'description'    => 'Description',
    'ean'            => 'EAN Code', // Korrekter Spaltenname aus der CSV
  ],

  // Mapping für die spezifischen Felder einer Produktvariante.
  // Diese werden in der `product_variations` Tabelle gespeichert.
  'variation_fields' => [
    'sku'    => 'Reference',
    'ean'    => 'EAN Code', // Korrekter Spaltenname
    'weight' => 'Product packed weight', // Korrekter Spaltenname
  ],

  // Mapping für die Attribute der Varianten (z.B. Farbe, Größe).
  // Daraus werden die Attribute und Attributwerte erstellt.
  'variation' => [
    'color' => 'Specifications', // Die Spalte 'Specifications' enthält die Farbe
    'size'  => 'Size',
  ],
];
