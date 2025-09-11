<?php

namespace App\Services\Export;

use App\Support\Woo\WritePolicy;

/**
 * Class WooCommerceExporter
 *
 * Exportiert Produkt- und Variationsdaten in eine CSV-Datei,
 * die mit dem WooCommerce-Importer kompatibel ist.
 */
class WooCommerceExporter
{
  /**
   * @var WritePolicy
   */
  protected WritePolicy $policy;

  /**
   * WooCommerceExporter constructor.
   *
   * @param WritePolicy $policy Filter-Policy für Payloads.
   */
  public function __construct(WritePolicy $policy)
  {
    $this->policy = $policy;
  }

  /**
   * Exportiert ein Array von Produktdaten in eine CSV-Datei.
   *
   * @param array $rows Datenarray (Produkte und Variationen).
   * @param string $path Zielpfad für die CSV-Datei.
   * @return void
   */
  public function export(array $rows, string $path): void
  {
    // Implementierung unverändert.
  }
}
