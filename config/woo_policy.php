<?php

/**
 * WooCommerce Write Policy Configuration
 *
 * Definiert, welche Felder niemals über die API gesetzt werden sollen.
 * Kann pro Hersteller erweitert werden.
 */
return [

  /**
   * Felder, die global nie geschrieben werden dürfen.
   * Preise werden grundsätzlich nicht über die API gesetzt.
   */
  'never_write' => [
    'regular_price',
    'sale_price',
    'price',
    '_price',
  ],

  /**
   * Hersteller-spezifische Write-Policies (Beispiele).
   */
  'manufacturers' => [
    'Edelrid' => [
      // Platzhalter für Hersteller-spezifische Regeln
    ],
  ],

];
