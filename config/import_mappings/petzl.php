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
    'designation'    => 'Designation',   // Marketing-/Katalogname
    'type'           => 'Type',
    'category'       => 'Category',
    'subcategory'    => 'Subcategory',
    'market'         => 'Market',
    'ean'            => 'EAN Code',
    'customs'        => 'Customs',       // Zolltarifnummer
    'made_in'        => 'Made in',
    'certification'  => 'CERTIFICATION',
    'materials'      => 'MATERIALS',
  ],

  // Mapping für die spezifischen Felder einer Produktvariante.
  // Diese werden in der `product_variations` Tabelle gespeichert.
  // Achtung: die Keys hier müssen zu deinen DB-Feldern / DTO-Feldern passen.
  'variation_fields' => [
    'sku'         => 'Reference',
    'ean'         => 'EAN Code',
    'weight'      => 'Product packed weight', // Rohgewicht (z.B. "2,21")
    'weight_unit' => 'Unit_weight',           // z.B. "KG"

    // Produkt-Abmessungen (Rohwerte + Einheiten)
    'length'      => 'Product length',        // z.B. "0,37"
    'length_unit' => 'Unit_length',           // z.B. "M"
    'width'       => 'Product Width',
    'width_unit'  => 'Unit_width',
    'height'      => 'Product Height',
    'height_unit' => 'Unit_height',

    // Verpackungsebene Karton (optional, falls du das später nutzen willst)
    'carton_qty'      => 'Qty Carton',
    'carton_length'   => 'Carton Length',
    'carton_width'    => 'Carton Width',
    'carton_height'   => 'Carton High',

    // Palette (ebenfalls optional, aber im CSV vorhanden)
    'palett_qty'      => 'Palett Quantity',
    'palett_length'   => 'Palett Length',
    'palett_width'    => 'Palett Width',
    'palett_height'   => 'Palett Heigh',
  ],

  // Mapping für die Attribute der Varianten (z.B. Farbe, Größe).
  // Daraus werden die Attribute und Attributwerte erstellt.
  'variation' => [
    // Petzl packt die Farbe in "Specifications", z.B. "black/yellow", "Black/Yellow", "Black"
    'color' => 'Specifications',
    // Größe ist meist eine Petzl-typische Size-Angabe ("0", "1", "2", S, M, L ...)
    'size'  => 'Size',
  ],

  /**
   * Optional: Metadaten für Maß-/Gewichtstransforms (analog Edelrid)
   *
   * Diese Struktur ist bewusst neutral gehalten. Deine Import-Logik kann
   * z.B. so etwas tun:
   * - Dezimalkomma → Dezimalpunkt
   * - KG → Gramm (int)
   * - M → Millimeter (int)
   *
   * Ob und wie du das nutzt, hängt vom GenericCsvProductImporter ab.
   */
  'measurements' => [
    'weight' => [
      'field'       => 'weight',       // Key aus variation_fields
      'unit_field'  => 'weight_unit',  // Key aus variation_fields
      'target_unit' => 'g',            // Interne Einheit (z.B. Gramm-Integer)
      'factor_from' => [
        'KG' => 1000,                  // 1 kg → 1000 g
        'G'  => 1,
      ],
    ],
    'length' => [
      'field'       => 'length',
      'unit_field'  => 'length_unit',
      'target_unit' => 'mm',           // 0,37 M → 370 mm
      'factor_from' => [
        'M'  => 1000,
        'CM' => 10,
        'MM' => 1,
      ],
    ],
    'width' => [
      'field'       => 'width',
      'unit_field'  => 'width_unit',
      'target_unit' => 'mm',
      'factor_from' => [
        'M'  => 1000,
        'CM' => 10,
        'MM' => 1,
      ],
    ],
    'height' => [
      'field'       => 'height',
      'unit_field'  => 'height_unit',
      'target_unit' => 'mm',
      'factor_from' => [
        'M'  => 1000,
        'CM' => 10,
        'MM' => 1,
      ],
    ],
  ],
];
