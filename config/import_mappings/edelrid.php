<?php

return [

  'group_by'  => ['Artikelbezeichnung', 'Artikelnummer'],
  'reference' => 'Artikelnummer',

  'product' => [
    // interner Anzeigename
    'product_name'      => 'Artikelbezeichnung',

    // optionaler Alias – kannst du drin lassen oder entfernen
    'name'              => 'Artikelbezeichnung',

    // HIER NEU: Produktnummer fürs Hauptprodukt
    'product_number'    => 'Artikelnummer',
    'description'       => 'Produkt-Text',
    'short_description' => "USP´s",
    'ean'               => 'EAN',
  ],

  'variation_fields' => [
    'sku' => 'Artikelnummer',
    'ean' => 'EAN',
    // 'ean' usw. bei Bedarf ergänzen
  ],

  // WICHTIG: richtige Spalten der CSV verwenden!
  // (Bezeichnung bevorzugt, Code als Fallback)
  'variation' => [
    'Farbe' => ['Farbe Bezeichnung', 'Farb-Code'],
    'Größe' => ['Größen Bezeichnung', 'Größen Code'],
  ],

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
