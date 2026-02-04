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
    'product_name'          => 'Product name',
    'original_product_name' => 'Product name',
    'product_number'        => 'Reference',
    'description'           => 'Description',
    'designation'           => 'Designation',   // Marketing-/Katalogname
    'type'                  => 'Type',
    'category'              => 'Category',
    'subcategory'           => 'Subcategory',
    'market'                => 'Market',
    'ean'                   => 'EAN Code',
    'customs'               => 'Customs',       // Zolltarifnummer
    'made_in'               => 'Made in',
    'certification'         => 'CERTIFICATION',
    'materials'             => 'MATERIALS',

    // Maße / Gewicht Produkt
    'dimension_length_mm' => 'Product length',
    'dimension_width_mm'  => 'Product  Width',    // zwei Leerzeichen
    'dimension_height_mm' => 'Product Height',
    'weight'              => 'Product  packed weight',

    // Kartonmaße
    'box_length'          => 'Carton Length',       // Karton-Länge in m → mm
    'box_width'           => 'Carton Width',        // Karton-Breite in m → mm
    'box_height'          => 'Carton High',         // Karton-Höhe in m → mm

    // 👉 Trigger für Transform:
    'dimensions_raw'      => 'Product length',
  ],

  'transforms' => [

    // Produktmaße: Meter → Millimeter
    'dimension_length_mm' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      // "0.37" oder "0,37" → Meter → Millimeter
      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    'dimension_width_mm' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    'dimension_height_mm' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    // Produktgewicht: kg → g
    'weight' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      // kg → g (2,10 → 2100)
      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },


    // Carton-Maße: Meter → Millimeter
    'box_length' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    'box_width' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    'box_height' => function ($value, array $row) {
      if ($value === null || $value === '') {
        return null;
      }

      $v = str_replace(',', '.', (string) $value);

      return (int) round(((float) $v) * 1000);
    },

    'dimensions_raw' => function ($resolved, array $row) {
      // Hilfsfunktion: CSV-Wert → float
      $toFloat = function ($raw) {
        if ($raw === null || $raw === '') {
          return null;
        }
        $norm = str_replace(',', '.', (string) $raw);
        if (!is_numeric($norm)) {
          return null;
        }
        return (float) $norm;
      };

      // Maße (Header-Namen wie nach der Normalisierung)
      $lenF = $toFloat($row['Product length']         ?? null); // m
      $widF = $toFloat($row['Product  Width']         ?? null); // m (2 Leerzeichen)
      $heiF = $toFloat($row['Product Height']         ?? null); // m

      $dimParts = [];

      if ($lenF !== null) {
        $dimParts[] = number_format($lenF, 2, ',', '') . ' m';
      }
      if ($widF !== null) {
        $dimParts[] = number_format($widF, 2, ',', '') . ' m';
      }
      if ($heiF !== null) {
        $dimParts[] = number_format($heiF, 2, ',', '') . ' m';
      }

      $dimStr = $dimParts ? implode(' × ', $dimParts) : null;

      // Gewicht: robusten Key-Finder verwenden
      $weightRaw = null;
      foreach (array_keys($row) as $key) {
        // Wir suchen nach irgendwas, das "Product", "packed" und "weight" enthält
        $lower = mb_strtolower($key);
        if (
          str_contains($lower, 'product') &&
          str_contains($lower, 'packed') &&
          str_contains($lower, 'weight')
        ) {
          $weightRaw = $row[$key];
          break;
        }
      }

      $wtF = $toFloat($weightRaw); // kg

      $wtStr = null;
      if ($wtF !== null) {
        $wtStr = number_format($wtF, 2, ',', '') . ' kg';
      }

      // Kombinieren
      if ($dimStr && $wtStr) {
        return $dimStr . ' / ' . $wtStr;
      }

      if ($dimStr) {
        return $dimStr;
      }

      if ($wtStr) {
        return $wtStr;
      }

      return null;
    },
  ],

  // Mapping für die spezifischen Felder einer Produktvariante.
  // Diese werden in der `product_variations` Tabelle gespeichert.
  // Achtung: die Keys hier müssen zu deinen DB-Feldern / DTO-Feldern passen.
  'variation_fields' => [
    'sku' => 'Reference',

    'ean' => 'EAN Code',

    'weight' => [
      'columns' => 'Product  packed weight', // kg
      'transform' => function ($value) {
        if ($value === null || $value === '') {
          return null;
        }
        // "2.21" oder "2,21" → kg → g
        $v = str_replace(',', '.', (string) $value);

        return (int) round(((float) $v) * 1000);
      },
    ],

    'length_mm' => [
      'columns' => 'Product length', // m
      'transform' => function ($value) {
        if ($value === null || $value === '') {
          return null;
        }
        $v = str_replace(',', '.', (string) $value);

        return (int) round(((float) $v) * 1000); // m → mm
      },
    ],

    'width_mm' => [
      'columns' => 'Product  Width', // m, Achtung: zwei Spaces
      'transform' => function ($value) {
        if ($value === null || $value === '') {
          return null;
        }
        $v = str_replace(',', '.', (string) $value);

        return (int) round(((float) $v) * 1000); // m → mm
      },
    ],

    'height_mm' => [
      'columns' => 'Product Height', // m
      'transform' => function ($value) {
        if ($value === null || $value === '') {
          return null;
        }
        $v = str_replace(',', '.', (string) $value);

        return (int) round(((float) $v) * 1000); // m → mm
      },
    ],
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
