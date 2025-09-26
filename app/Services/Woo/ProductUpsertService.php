<?php

namespace App\Services\Woo;

use App\Models\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Shop;
use Illuminate\Support\Arr;


/**
 * Class ProductUpsertService
 *
 * Zweck:
 * - Orchestriert das Anlegen/Aktualisieren einzelner Produkte oder Varianten in WooCommerce.
 * - Bindet den WooParentResolver (SKU-first) vor der eigentlichen Create/Update-Operation ein,
 *   damit bestehende Einträge erkannt und korrekt aktualisiert werden.
 *
 * Verwendung (Beispiel):
 *   $result = app(ProductUpsertService::class)->upsert($shop, [
 *       'sku'        => 'PETZL-A010EA00-RED-L',
 *       'ean'        => '3342540833561',
 *       'mpn'        => 'A010EA00',
 *       'brand'      => 'Petzl',
 *       'attributes' => ['color' => 'RED', 'size' => 'L'], // für Varianten
 *       'payload'    => [...],                              // Woo-Payload für Produkt/Variation
 *       'parent_payload' => [...],                          // optional: Parent-Payload, falls Variante ohne bestehenden Parent
 *   ]);
 *
 * Rückgabe (vereinfacht):
 * - [
 *     'action'       => 'update_product'|'update_variation'|'create_product'|'create_variation',
 *     'product_id'   => int|null,
 *     'variation_id' => int|null,
 *     'matched_by'   => 'sku'|'ean'|'mpn'|'composite'|null,
 *     'notes'        => string[],
 *     'result'       => array|null   // API-Antwort des Woo-Clients
 *   ]
 *
 * Hinweise:
 * - SKU ist laut Kunden-Setup der primäre Match-Key (99,98 % Eindeutigkeit).
 * - Fallbacks (EAN/MPN/Composite-SKU) greifen nur, wenn am Kandidaten keine SKU vorhanden ist.
 * - Logging erfolgt auf DEBUG-Level. Setze LOG_LEVEL=debug für detaillierte Ausgabe.
 *
 * @package App\Services\Woo
 */
class ProductUpsertService
{
  /** @var WooRepositoryFactory */
  protected WooRepositoryFactory $repoFactory;

  /**
   * @param WooRepositoryFactory $repoFactory  Erzeugt Repo pro Shop (WooClient + WooApiRepository).
   */
  public function __construct(WooRepositoryFactory $repoFactory)
  {
    $this->repoFactory = $repoFactory;
  }

  /**
   * Legt ein Produkt/Variante neu an oder aktualisiert es – mit vorheriger Parent/Variation-Auflösung.
   *
   * @param  Shop  $shop       Ziel-Shop (enthält base_url, api_version, consumer_key, consumer_secret)
   * @param  array $candidate  Siehe Klassendoku für das erwartete Format.
   * @return array             Struktur mit Aktion, IDs, Notizen und Woo-API-Ergebnis.
   */
  public function upsert(Shop $shop, array $candidate): array
  {
    // 1) Repo + Resolver + Orchestrator für diesen Shop aufbauen
    $repo      = $this->repoFactory->make($shop);
    $resolver  = new WooParentResolver($repo);
    $orchestrator = new ProductExportOrchestrator($resolver, $repo);

    Log::debug('ProductUpsertService: begin upsert', [
      'shop_id' => $shop->id ?? null,
      'has_sku' => Arr::has($candidate, 'sku'),
    ]);

    // 2) Export/Sync (inkl. Resolve inside Orchestrator)
    $result = $orchestrator->exportOne($candidate);

    Log::debug('ProductUpsertService: finished upsert', [
      'action'       => $result['action'] ?? null,
      'product_id'   => $result['product_id'] ?? null,
      'variation_id' => $result['variation_id'] ?? null,
      'matched_by'   => $result['matched_by'] ?? null,
    ]);

    return $result;
  }
}
