<?php

/**
 * Hersteller-Mapping: Edelrid
 *
 * Dieses Mapping sorgt dafür, dass die Edelrid-Daten nicht länger wie
 * eine Mischung aus Kletterlatein, Excel-Akrobatik und Marketinglyrik
 * aussehen, sondern brav in unser internes Produktmodell passen.
 *
 * Pfad: config/import_mappings/edelrid.php
 *
 * Verantwortlichkeiten:
 * - Zuordnung der Edelrid-Felder zu unseren internen Attributen
 * - Normalisierung von Farben, Größen, Beschreibungen
 * - Gruppierung von Varianten (z. B. Seillänge, Farbe)
 *
 * Hinweis:
 * Wenn Edelrid spontan eine Spalte umbenennt, wirst du es sofort hier merken.
 *
 * @mapping-source   Edelrid CSV/XML Feeds
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForEdelrid
 */
use App\Support\ImportValueNormalizer as V;

return [

  /*
    |--------------------------------------------------------------------------
    | Edelrid Import Mapping (CSV) – Stand: Kopfzeile aus deinem Beispiel
    |--------------------------------------------------------------------------
    | - reference/group_by     : Gruppierung pro Produktfamilie
    | - fields                 : CSV → interne Felder (ohne Preise)
    | - sku / sku_compose      : base-SKU + optionale Komposition für Varianten
    | - attributes             : CSV-Attribute → Woo-Attribute (pa_*)
    | - variation_fields       : Variantenachsen (Farbe, Größe)
    | - images / videos        : Medienfelder
    | - transforms             : Normalisierung (g, mm, URL-Listen, EAN)
    | - rules                  : Kurzbeschreibung-Fallback, Preise ignorieren
    */

  // Identifikation und Gruppierung
  'reference' => 'Artikelnummer',
  'group_by'  => 'Artikelbezeichnung',

  // Basismapping (CSV → interne Zielfelder)
  'fields' => [
    'product_number'     => ['Artikelnummer'],
    'product_name'       => ['Artikelbezeichnung'],
    'description'        => ['Produkt-Text', "USP´s"], // Langtext + USP als Fallback
    'short_description'  => ['Kurzbeschreibung'],       // wird via Regel aus description ergänzt, falls leer

    'ean'                => ['EAN'],

    // Maße
    'dimensions_raw'     => ['size'],

    // Gewicht in g
    'weight'             => ['Gewicht ohne Verpackung (g)'],

    // Material/Normen/Links
    'material'           => ['Materialzusammensetzung'],
    'norm'               => ['DIN'],
    'instruction_url'    => ['URL Gebrauchsanleitung'],
    'declaration_url'    => ['URL Konformitätserklärung'],

    // Medien (Bilder in mehreren Spalten; Videos ggf. als Zeilenliste)
    'image_urls'         => [
      'URL Produktbild Detailbild 0',
      'URL Produktbild Detailbild 1',
      'URL Produktbild Detailbild 2',
      'URL Produktbild Detailbild 3',
      'URL Produktbild Detailbild 4',
      'URL Produktbild Detailbild 5',
      'URL Produktbild Detailbild 6',
      'URL Produktbild Detailbild 7',
      'URL Produktbild Detailbild 8',
      'URL Produktbild Detailbild 9',
      'URL Produktbild Detailbild 10',
    ],
    'video_urls'         => ['Videolink'],

    // SKU: Basisfeld (Artikelnummer) – Details siehe 'sku_compose'
    'sku'                => ['Artikelnummer'],
  ],

  /**
   * ✅ Vom GenericCsvProductImporter erwartetes Produkt-Mapping.
   * Wir belassen dein bestehendes 'fields' unangetastet und liefern hier nur das,
   * was der Importer aktiv nutzt.
   */
  'product' => [
    'product_number'     => ['Artikelnummer'],
    'product_name'       => ['Artikelbezeichnung'],
    'description'        => ['Produkt-Text', "USP´s"],
    'short_description'  => ['Kurzbeschreibung'],
    'ean'                => ['EAN'],
    'weight'             => ['Gewicht ohne Verpackung (g)'],
    'material'           => ['Materialzusammensetzung'],
    'norm'               => ['DIN'],
    'instruction_url'    => ['URL Gebrauchsanleitung'],
    'declaration_url'    => ['URL Konformitätserklärung'],

    // Maße: in der CSV taucht häufig am Ende z. B. „130 x 76“ auf → als Rohwert einlesen
    'dimensions_raw'     => ['size'],

    'image_urls' => [
        'URL Produktbild Detailbild 0',
    ],
  ],

  // SKU-Komposition für Varianten (Importer kann daraus eine eindeutige Varianten-SKU bauen)
  // Ergebnis-Form: Artikelnummer[-Farb-Code[-Größen Code]]
  'sku_compose' => [
    'fields'    => ['Artikelnummer', 'Farb-Code', 'Größen Code'],
    'separator' => '-',   // z. B. 72022-138   oder   88230-15-47
    // Falls dein Importer aktuell keine sku_compose liest:
    // Er nimmt wenigstens 'fields.sku' (Artikelnummer) als Basis-SKU.
  ],

  // Attribute (CSV-Spalten → Woo-Attribute-Slugs)
  'attributes' => [
    'Farbe Bezeichnung'      => 'pa_color',
    'Farb-Code'              => 'pa_color_code',

    'Größen Bezeichnung'     => 'pa_size',
    'Größen Code'            => 'pa_size_code',

    'Geschlecht'             => 'pa_gender',
    'Einsatzbereich'         => 'pa_einsatzbereich',
    'Materialzusammensetzung' => 'pa_material',
    'Closure'                => 'pa_closure',
    'DIN'                    => 'pa_norm',

    // JAderBass 2025-11-22
    'Marke'                  => 'pa_brand',

    // Nachhaltigkeit/Flags
    'Climb Green'            => 'pa_climbgreen',
    '100% vegan'             => 'pa_vegan',
    'Made in Germany'        => 'pa_origin',

    // Seil-/Hardware-Spezifika (falls befüllt)
    'diameter'               => 'pa_diameter',
    'sheath proportion'      => 'pa_sheath_proportion',
    'number of falls'        => 'pa_number_of_falls',
    'impact of force'        => 'pa_impact_of_force',
    'dynamic elongation'     => 'pa_dynamic_elongation',
    'Static elongation'      => 'pa_static_elongation',
    'sheath slippage'        => 'pa_sheath_slippage',
    'max. breaking strength ' => 'pa_max_breaking_strength',
    'gate opening'           => 'pa_gate_opening',
    'f. max vert.'           => 'pa_f_max_vert',
    'f. max minor axis'      => 'pa_f_max_minor_axis',
    'f max. open'            => 'pa_f_max_open',
    'waist'                  => 'pa_waist',
    'LEG LOOPS'              => 'pa_leg_loops',
  ],

  // Variationsachsen (bestimmen, wie Zeilen zu Varianten gruppiert werden)
  'variation_fields' => [
    'Farbe Bezeichnung',
    'Größen Bezeichnung',
  ],

  /**
   * ✅ Vom Importer erwartete Varianten-Felder (assoziativ):
   * key = DB-Feld der Variation, value = CSV-Spalte(n)
   */
  'variation_fields' => [
    'color_name' => ['Farbe Bezeichnung'],
    'size_name'  => ['Größen Bezeichnung'],
  ],

  /**
   * ✅ Vom Importer erwartetes Attribut-Mapping (für Pivot).
   * key = Anzeigename des Attributes, value = CSV-Spalte(n)
   */
  'variation' => [
    'Farbe' => ['Farbe Bezeichnung'],
    'Größe' => ['Größen Bezeichnung'],
  ],

  // Medienzusammenführung
  'images' => [
    'columns'  => [
      'URL Produktbild Detailbild 0',
      'URL Produktbild Detailbild 1',
      'URL Produktbild Detailbild 2',
      'URL Produktbild Detailbild 3',
      'URL Produktbild Detailbild 4',
      'URL Produktbild Detailbild 5',
      'URL Produktbild Detailbild 6',
      'URL Produktbild Detailbild 7',
      'URL Produktbild Detailbild 8',
      'URL Produktbild Detailbild 9',
      'URL Produktbild Detailbild 10',
    ],
    'split_on' => [',', ';', '|'],
    'validate' => true,
    'dedupe'   => true,
  ],

  // Normalisierungen / Umrechnungen
  'transforms' => [
    // Trims
    'product_name' => [\App\Support\Import\Edelrid\Map::class, 'productName'],
    'description'  => [\App\Support\Import\Edelrid\Map::class, 'description'],

    // EAN nur Ziffern
    'ean'          => [\App\Support\Import\Edelrid\Map::class, 'ean'],

    // Gewicht in Gramm
    'weight'       => [\App\Support\Import\Edelrid\Map::class, 'weightGrams'],

    // Roh-Dimensionen (z. B. "130 x 76") → einzelne mm-Werte
    'dimensions_raw' => [\App\Support\Import\Edelrid\Map::class, 'dimensionsRaw'],

    // Bild- und Video-URLs normalisieren
    'image_urls'   => [\App\Support\Import\Edelrid\Map::class, 'imageUrls'],
    'video_urls'   => [\App\Support\Import\Edelrid\Map::class, 'videoUrls'],

    // SKU-Basis bleibt 'Artikelnummer'; falls dein Importer Row-aware-Transforms unterstützt,
    // kannst du alternativ eine Komposition direkt hier definieren:
    // 'sku' => function ($v, $row) { ... }
  ],

  // Import-Regeln
  'rules' => [
    // Falls short_description leer → ersten Satz aus description übernehmen
    'short_desc_from_first_sentence_if_missing' => true,

    // Preise generell ignorieren (Projektvorgabe)
    'ignore_prices' => true,

    // (optional, später) automatische Übersetzung
    'translate_to_de' => false,
  ],
];
