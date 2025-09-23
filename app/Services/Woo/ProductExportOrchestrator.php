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
 * Regeln zur SKU:
 * - variable (Parent):   NIE eine SKU setzen (auch nicht aus product_number)!
 * - simple:              Primär products.sku, Fallback products.product_number (falls vorhanden)
 *
 * Hintergrund:
 * - Woo verlangt globale Eindeutigkeit für SKUs (Produkt + Varianten).
 * - Best Practice: Für variable Eltern keine SKU; SKUs leben auf Variantenebene.
 *
 * Integration:
 * - app(ProductExportOrchestrator::class)->syncSingle($product, failHard: false);
 *
 * Hinweis zu Feldern:
 * - Lokales DB-Feld: products.product_type ('simple'|'variable')
 * - Optional vorhandenes Feld: products.product_number (kann als Fallback für simple dienen)
 * - Preise bleiben unberührt (werden nicht synchronisiert).
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
   * Synchronisiert genau ein Produkt (Create/Update, abhängig von Preflight/woo_product_id).
   *
   * @param  Product $product
   * @param  bool    $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function syncSingle(Product $product, bool $failHard = false): array
  {
    // --- 1) Produkttyp bestimmen ---------------------------------------
    $type = $product->product_type ?: ($product->variations()->exists() ? 'variable' : 'simple');
    if (!in_array($type, ['simple', 'variable'], true)) {
      $type = $product->variations()->exists() ? 'variable' : 'simple';
    }

    // --- 2) Basis-Payload (OHNE Preise) ---------------------------------
    $payload = [
      'type'              => $type, // Woo erwartet 'type'
      'name'              => (string) ($product->product_name ?? $product->name ?? "Product {$product->id}"),
      'slug'              => (string) ($product->slug ?? ''), // optional
      'status'            => 'publish',                       // oder 'draft'
      'description'       => (string) ($product->description ?? ''),
      'short_description' => (string) ($product->short_description ?? ''),
    ];

    // --- 3) SKU-Regel streng durchsetzen -------------------------------
    $chosenSku = null;

    if ($type === 'simple') {
      // Primär products.sku verwenden, falls vorhanden…
      if (!empty($product->sku)) {
        $chosenSku = (string) $product->sku;
      }
      // …ansonsten Fallback: products.product_number (falls vorhanden)
      elseif (!empty($product->product_number)) {
        $chosenSku = (string) $product->product_number;
      }

      if (!empty($chosenSku)) {
        $payload['sku'] = $chosenSku;
      }
    } else {
      // type === 'variable' -> Parent-SKU NIE setzen (auch nicht als Fallback)
      // Zusätzlich: falls irrtümlich irgendwoher eine SKU im Modell hängt, NICHT übernehmen.
    }

    // --- 4) Bilder (optional) ------------------------------------------
    if (!empty($product->image_url)) {
      $payload['images'] = [
        ['src' => (string) $product->image_url],
      ];
    }

    // --- 5) Logging & Upsert -------------------------------------------
    Log::info('ProductExportOrchestrator: upserting product', [
      'product_id'        => $product->id,
      'woo_product_id'    => $product->woo_product_id,
      'type'              => $type,
      'parent_sku_decision' => $type === 'variable'
        ? 'no-parent-sku (variable parent)'
        : ('simple: using ' . ($chosenSku !== null
          ? (isset($payload['sku']) && $product->sku === $chosenSku ? 'products.sku' : 'products.product_number')
          : 'none')),
      'sku_value'         => $chosenSku,
    ]);

    $result = $this->upsert->upsertProduct($product, $payload, $failHard);

    Log::info('ProductExportOrchestrator: upsert result', [
      'product_id' => $product->id,
      'result'     => $result,
    ]);

    Log::debug($payload);

    return $result;
  }
}
