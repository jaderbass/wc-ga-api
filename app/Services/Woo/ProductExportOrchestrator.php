<?php

namespace App\Services\Woo;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * ProductExportOrchestrator
 *
 * Orchestriert den Outbound-Sync eines Hauptprodukts zu WooCommerce
 * unter Nutzung des SKU-Preflights (ProductUpsertService).
 *
 * Eigenschaften:
 * - Baut einen konservativen, Woo-kompatiblen Payload für /products (OHNE Preise).
 * - Unterstützt "simple" und "variable" Produkt-Typen.
 * - Verzichtet bewusst auf Parent-SKU bei "variable", um Kollisionen mit Varianten-SKUs zu vermeiden.
 * - Nutzt ProductUpsertService::upsertProduct() (Preflight via WooProductLookupService inklusive).
 *
 * Integration:
 * - Anstelle direkter POST/PUT-Calls im bisherigen Exporter:
 *     app(ProductExportOrchestrator::class)->syncSingle($product, failHard: false);
 *
 * Hinweise:
 * - DB-Feld heißt lokal `products.product_type` (ENUM 'simple'|'variable').
 *   Im Woo-Payload bleibt das Feld **`type`**.
 * - Passe ggf. Feldnamen (name/description/slug/bilder) an deine Struktur an.
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class ProductExportOrchestrator
{
  public function __construct(
    protected ProductUpsertService $upsert,
  ) {}

  /**
   * Synchronisiert ein Parent-Produkt mit WooCommerce.
   *
   * Robustheit:
   * - Wenn Woo 400 "woocommerce_rest_product_invalid_id" zurückgibt (veraltete/nicht existente ID),
   *   setzen wir lokal woo_product_id = null und versuchen CREATE erneut.
   *
   * @param  Product $product
   * @param  bool    $failHard  true = Exceptions durchreichen
   * @return array<string,mixed>
   */
  public function syncSingle(Product $product, bool $failHard = false): array
  {
    /** @var \App\Services\Woo\ProductUpsertService $upsert */
    $upsert = app(\App\Services\Woo\ProductUpsertService::class);

    $attempt = function () use ($upsert, $product, $failHard): array {
      // Hinweis: Diese Methode sollte intern entscheiden "create vs update"
      // anhand von $product->woo_product_id.
      return $upsert->upsert($product, $failHard);
    };

    try {
      return $attempt();
    } catch (\Throwable $e) {
      $msg = $e->getMessage();

      // Erkenne den bekannten Woo-Fehler (invalid id)
      $isInvalidId =
        str_contains($msg, 'woocommerce_rest_product_invalid_id') ||
        str_contains($msg, 'Invalid ID') ||
        str_contains($msg, '"code":"woocommerce_rest_product_invalid_id"');

      if ($isInvalidId) {
        $oldId = $product->woo_product_id;
        Log::warning('Orchestrator: invalid woo_product_id detected, retrying as create', [
          'product_id'      => $product->id,
          'old_woo_id'      => $oldId,
          'exception_class' => get_class($e),
          'exception_msg'   => $msg,
        ]);

        // Lokale ID leeren und persistieren → nächster Upsert wird CREATE
        $product->woo_product_id = null;
        $product->save();

        // Zweiter Versuch als CREATE
        try {
          $res = $attempt();

          // Bei Erfolg: neue ID aus Response in Produkt persistieren, falls vorhanden
          $newId = $res['id'] ?? ($res['woo_product_id'] ?? null);
          if ($newId && (int)$newId !== (int)$oldId) {
            $product->woo_product_id = (int) $newId;
            $product->save();
          }

          return $res;
        } catch (\Throwable $e2) {
          Log::error('Orchestrator: retry after invalid_id failed', [
            'product_id'    => $product->id,
            'old_woo_id'    => $oldId,
            'exception_msg' => $e2->getMessage(),
          ]);
          if ($failHard) {
            throw $e2;
          }

          return [
            'status'  => 'error',
            'message' => $e2->getMessage(),
            'action'  => 'retry-create-failed',
          ];
        }
      }

      // anderer Fehler → optional durchreichen
      if ($failHard) {
        throw $e;
      }

      Log::error('Orchestrator: product sync failed', [
        'product_id'    => $product->id,
        'message'       => $msg,
      ]);

      return [
        'status'  => 'error',
        'message' => $msg,
        'action'  => 'failed',
      ];
    }
  }

  /**
   * Synchronisiert alle Varianten eines Produkts mit WooCommerce.
   *
   * Zentraler Einstiegspunkt für den Variantensync – analog zum Parent-Upsert.
   * Nutzt den VariationSyncService mit der aktuellen Signatur
   *   syncProduct(Product $product, bool $failHard = false)
   * und normalisiert die Rückgabe für die Filament-UI.
   *
   * @param  \App\Models\Product  $product
   * @return array{
   *   product_id:int,
   *   variants:int,
   *   created:int,
   *   updated:int,
   *   skipped:int,
   *   errors:int,
   *   details: array<int, array<string,mixed>>
   * }
   */
  public function syncVariationsForProduct(\App\Models\Product $product): array
  {
    /** @var \App\Services\Woo\VariationSyncService $svc */
    $svc = app(\App\Services\Woo\VariationSyncService::class);

    try {
      // Aktuelle Service-Signatur: (Product $product, bool $failHard = false)
      $res = $svc->syncProduct($product, false);
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::error('Orchestrator: variant sync failed', [
        'product_id' => $product->id,
        'message'    => $e->getMessage(),
      ]);
      throw $e;
    }

    $created  = (int) ($res['created'] ?? 0);
    $updated  = (int) ($res['updated'] ?? 0);
    $skipped  = (int) ($res['skipped'] ?? 0);
    $errors   = (int) ($res['errors']  ?? 0);
    $variants = $created + $updated + $skipped;

    return [
      'product_id' => $product->id,
      'variants'   => $variants,
      'created'    => $created,
      'updated'    => $updated,
      'skipped'    => $skipped,
      'errors'     => $errors,
      'details'    => $res['details'] ?? [],
    ];
  }
}
