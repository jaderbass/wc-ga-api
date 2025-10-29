<?php

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
  'group_by'  => ['Artikelnummer'],

  // Basismapping (CSV → interne Zielfelder)
  'fields' => [
    'product_number'     => ['Artikelnummer'],
    'product_name'       => ['Artikelbezeichnung'],
    'description'        => ['Produkt-Text', "USP´s"], // Langtext + USP als Fallback
    'short_description'  => ['Kurzbeschreibung'],       // wird via Regel aus description ergänzt, falls leer

    'ean'                => ['EAN'],

    // Gewicht in g
    'weight_g'           => ['Gewicht ohne Verpackung (g)'],

    // Material/Normen/Links
    'material'           => ['Materialzusammensetzung'],
    'norm'               => ['DIN'],
    'instruction_url'    => ['URL Gebrauchsanleitung'],
    'declaration_url'    => ['URL Konformitätserklärung'],

    // Maße: in der CSV taucht häufig am Ende z. B. „130 x 76“ auf → als Rohwert einlesen
    'dimensions_raw'     => ['size'],

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
    'product_name' => fn($v) => is_string($v) ? trim($v) : $v,
    'description'  => fn($v) => is_string($v) ? trim($v) : $v,

    // EAN nur Ziffern
    'ean'          => fn($v) => preg_replace('/\D+/', '', (string) $v) ?: null,

    // Gewicht in Gramm
    'weight_g'     => fn($v) => V::toGrams($v),

    // Roh-Dimensionen (z. B. "130 x 76") → einzelne mm-Werte
    'dimensions_raw' => function ($v) {
      if (!$v) return null;
      $s = preg_replace('/[^0-9xX,.\s]/', '', (string)$v);
      $parts = preg_split('/[xX]/', $s);
      $parts = array_map(fn($p) => V::toMillimeters(trim($p)), $parts);
      $parts = array_values(array_filter($parts, fn($n) => $n !== null));
      return $parts ?: null; // z. B. [130, 76]
    },

    // Bild- und Video-URLs normalisieren
    'image_urls'   => fn($v) => V::normalizeUrlList($v, [',', ';', '|']),
    'video_urls'   => fn($v) => V::normalizeUrlList($v, [',', ';', "\n"]),

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
