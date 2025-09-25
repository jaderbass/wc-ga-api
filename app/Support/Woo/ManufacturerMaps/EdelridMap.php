<?php

namespace App\Support\Woo\ManufacturerMaps;

/**
 * Class EdelridMap
 *
 * Definiert die Zuordnung der internen Produktfelder zu
 * WooCommerce-Spalten für den Hersteller Edelrid.
 */
class EdelridMap
{
  /**
   * Gibt das Mapping zurück.
   *
   * @return array<string, string> Array von WooCommerce-Spalte => internes Feld.
   */
  public static function map(): array
  {
    return [
      'name'  => 'product_name',
      'sku'   => 'product_number',
      'ean'   => 'ean',
      'slug'  => 'slug',
      'type'  => 'product_type',
    ];
  }
}
