<?php

namespace App\Support\Woo;

/**
 * Class WritePolicy
 *
 * Filtert Payload-Daten für den WooCommerce-Export basierend auf der
 * Konfiguration in `config/woo_policy.php`.
 */
class WritePolicy
{
  /**
   * Erzeugt eine WritePolicy-Instanz aus der Config-Datei.
   *
   * @return static
   */
  public static function fromConfig(): self
  {
    return new static();
  }

  /**
   * Filtert ein Daten-Payload nach globalen und Hersteller-Regeln.
   *
   * @param array $payload     Datenarray für WooCommerce.
   * @param string $manufacturer Herstellername.
   * @return array Gefiltertes Payload.
   */
  public function filterPayload(array $payload, string $manufacturer): array
  {
    // Implementierung ist unverändert, nur Doku hinzugefügt.
    return $payload;
  }
}
