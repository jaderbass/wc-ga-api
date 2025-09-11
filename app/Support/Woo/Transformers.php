<?php

namespace App\Support\Woo;

/**
 * Class Transformers
 *
 * Sammlung von statischen Hilfsfunktionen zur Transformation
 * von Produkt- und Variationsdaten in WooCommerce-kompatibles Format.
 */
class Transformers
{
  /**
   * Beispiel: Wandelt Millimeter in Zentimeter um.
   *
   * @param int|float|null $value Wert in Millimeter.
   * @return float|null Wert in Zentimeter oder null.
   */
  public static function mmToCm(?float $value): ?float
  {
    return $value !== null ? $value / 10.0 : null;
  }

  /**
   * Beispiel: Wandelt Gramm in Kilogramm um.
   *
   * @param int|float|null $value Wert in Gramm.
   * @return float|null Wert in Kilogramm oder null.
   */
  public static function gToKg(?float $value): ?float
  {
    return $value !== null ? $value / 1000.0 : null;
  }
}
