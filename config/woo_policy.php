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

  /**
   * Woo Policy / Kompatibilitäts-Schalter
   *
   * - compat.wp_all_export_woo_addon:
   *   Einige Versionen des "WP All Export – Woo Add-on" injizieren/erwarten REST-Felder
   *   (z. B. attributes/options) und können POST /products blockieren.
   *   Wenn true, entfernt der PayloadBuilder riskante Felder bei einfachen Produkten.
   */
  'compat' => [
    'wp_all_export_woo_addon' => true,
  ],

];
