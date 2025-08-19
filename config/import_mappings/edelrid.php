<?php

/**
 * Mapping für EDELRID-CSV → interne Felder
 *
 * Wird vom GenericCsvProductImporter über config('import_mappings.edelrid') geladen.
 *
 * Hinweise:
 * - Wir setzen sowohl 'product_name' als auch 'name' auf 'Artikelbezeichnung', damit
 *   der Importer flexibel bleibt (je nach erwarteten Keys).
 * - 'variation' nutzt jetzt Fallback-Arrays (Bezeichnung → Code). Dein
 *   GenericCsvProductImporter sollte dafür die firstNonEmpty-Logik unterstützen.
 * - 'images.columns' listet die Bild-Header (0..10); nutze das in deinem Importer,
 *   falls er Bilder aggregieren kann.
 */

return [

  // Alle Zeilen mit gleichem Produktnamen bilden ein Hauptprodukt mit Varianten
  'group_by'  => 'Artikelbezeichnung',

  // Eindeutige Referenz der Variante (Upsert-Key)
  'reference' => 'Artikelnummer',

  // Felder für das Hauptprodukt
  'product' => [
    'product_name'      => 'Artikelbezeichnung',
    'name'              => 'Artikelbezeichnung',

    'description'       => 'Produkt-Text',
    'short_description' => "USP´s",      // optional – nur wenn dein Model das Feld hat
    'ean'               => 'EAN',        // falls EAN im Hauptprodukt gespeichert wird

    // nur eintragen, wenn dein Product-Model diese Felder hat:
    // 'brand'         => 'Marke',
    // 'product_group' => 'Produktgruppe',
  ],

  // Direkte Variantenspalten (nur angeben, wenn diese Felder bei dir existieren)
  'variation_fields' => [
    'sku' => 'Artikelnummer',
    // Beispiele – erst eintragen, wenn vorhanden:
    // 'ean'            => 'EAN',
    // 'regular_price'  => 'VK Netto',
    // 'sale_price'     => 'UVP',
    // 'stock_quantity' => 'Bestand',
  ],

  // Attribut-Mapping (mit Fallbacks: zuerst Bezeichnung, wenn leer → Code)
  'variation' => [
    'Farbe' => ['Farbe Bezeichnung', 'Farb-Code'],
    'Größe' => ['Größen Bezeichnung', 'Größen Code'],
  ],

  // Bilder (optional)
  'images' => [
    'columns' => [
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
  ],
];
