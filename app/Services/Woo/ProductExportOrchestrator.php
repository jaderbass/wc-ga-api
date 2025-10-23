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
   * - Baut das Woo-/products-Payload (ohne Preise).
   * - Ruft ProductUpsertService::upsertProduct($product, $payload, $failHard) auf.
   * - Bei "woocommerce_rest_product_invalid_id": woo_product_id = null setzen und Create erneut versuchen.
   *
   * @param  Product $product
   * @param  bool    $failHard
   * @return array<string,mixed>
   */
  public function syncSingle(Product $product, bool $failHard = false): array
  {
    /** @var \App\Services\Woo\ProductUpsertService $upsert */
    $upsert = app(\App\Services\Woo\ProductUpsertService::class);

    // 1) Payload für Woo /products aufbauen (minimal & lokal)
    $payload = $this->makeMinimalProductPayload($product);

    $attempt = function () use ($upsert, $product, $payload, $failHard): array {
      return $upsert->upsertProduct($product, $payload, $failHard);
    };

    try {
      $res    = $attempt();
      $remote = $res['remote_id'] ?? null;

      if (!$product->woo_product_id && $remote) {
        $product->woo_product_id = (int) $remote;
        $product->save();
      }

      return $res;
    } catch (\Throwable $e) {
      $msg = $e->getMessage();

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

        // 2) Lokale ID leeren → nächster Upsert wird POST
        $product->woo_product_id = null;
        $product->save();

        // Payload ggf. neu (hier identisch, aber sauber)
        $payload = $this->makeMinimalProductPayload($product);

        try {
          $res    = $upsert->upsertProduct($product, $payload, $failHard);
          $remote = $res['remote_id'] ?? null;

          if ($remote && (int) $remote !== (int) $oldId) {
            $product->woo_product_id = (int) $remote;
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

      if ($failHard) {
        throw $e;
      }

      Log::error('Orchestrator: product sync failed', [
        'product_id' => $product->id,
        'message'    => $msg,
      ]);

      return [
        'status'  => 'error',
        'message' => $msg,
        'action'  => 'failed',
      ];
    }
  }

  /**
   * Baut ein minimales, valides Woo-/products-Payload direkt aus dem lokalen Produkt.
   * - Keine Preise
   * - Für variable Parents standardmäßig KEINE SKU (Woo-Best-Practice)
   * - Leere Felder werden entfernt
   *
   * @return array<string,mixed>
   */
  private function makeMinimalProductPayload(Product $product): array
  {
    $type = $product->product_type ?? 'simple';
    $isVariable = $type === 'variable';

    // Felde-Namen ggf. an Dein Model anpassen:
    $name        = $product->name ?? ('Product #' . $product->id);
    $description = $product->description ?? '';
    $short       = property_exists($product, 'short_description') ? ($product->short_description ?? '') : '';
    $sku         = $isVariable ? null : ($product->sku ?? null); // Parent ohne SKU bei variable

    $payload = [
      'name'              => $name,
      'type'              => in_array($type, ['simple', 'variable'], true) ? $type : 'simple',
      'description'       => $description,
      'short_description' => $short,
      'sku'               => $sku,
      'status'            => 'publish',
    ];

    // Leere/null entfernen
    return array_filter($payload, static fn($v) => !($v === null || $v === ''));
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
