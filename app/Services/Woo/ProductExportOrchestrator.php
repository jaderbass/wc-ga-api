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
   * Synchronisiert genau ein Produkt (Create/Update, abhängig von Preflight/woo_product_id).
   *
   * @param  Product $product
   * @param  bool    $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function syncSingle(Product $product, bool $failHard = false): array
  {
    // --- 1) Produkt-Typ bestimmen --------------------------------------
    // Primär: aus DB-Feld `product_type` ('simple'|'variable').
    // Fallback: Heuristik über vorhandene Varianten.
    $type = $product->product_type ?: ($product->variations()->exists() ? 'variable' : 'simple');
    if (!in_array($type, ['simple', 'variable'], true)) {
      $type = $product->variations()->exists() ? 'variable' : 'simple';
    }

    // --- 2) Basis-Payload bauen (OHNE Preise) ----------------------------
    $payload = [
      'type'        => $type, // Woo erwartet 'type', nicht 'product_type'
      'name'        => (string) ($product->product_name ?? $product->name ?? "Product {$product->id}"),
      'slug'        => (string) ($product->slug ?? ''), // optional
      'status'      => 'publish',                       // oder 'draft'
      'description' => (string) ($product->description ?? ''),
      'short_description' => (string) ($product->short_description ?? ''),
    ];

    // SKU nur bei "simple" setzen (Best Practice: Parent ohne SKU bei "variable")
    if ($type === 'simple' && !empty($product->sku)) {
      $payload['sku'] = (string) $product->sku;
    }

    // --- 3) Bilder (optional) -------------------------------------------
    if (!empty($product->image_url)) {
      $payload['images'] = [
        ['src' => (string) $product->image_url],
      ];
    }

    // --- 3b) (NEU) Parent-Attribute für variable Produkte setzen --------
    if ($type === 'variable' && $product->variations()->exists()) {
      $attributeMap = config('woo.mapping.variation_attribute_map', [
        'size'          => 'Size',
        'color'         => 'Color',
        'length'        => 'Length',
        'certification' => 'Certification',
        'grosse'        => 'Size',
        'groesse'       => 'Size',
        'farbe'         => 'Color',
      ]);

      /** @var \App\Models\Shop $shop */
      $shop = \App\Models\Shop::query()->first();
      $resolver = new \App\Services\Woo\WooAttributeResolver($shop);

      $parentAttributes = [];
      foreach ($attributeMap as $internalKey => $wooLabel) {
        $resolved = $resolver->resolve($internalKey);
        if (!$resolved) continue;

        $options = $product->variations()
          ->pluck($internalKey)
          ->filter(fn($v) => $v !== null && $v !== '')
          ->unique()->values()->all();

        if (empty($options)) continue;

        $parentAttributes[] = [
          'id'        => $resolved['id'],
          'name'      => $resolved['name'],
          'options'   => array_values($options),
          'visible'   => true,
          'variation' => true,
        ];
      }

      if (!empty($parentAttributes)) {
        $payload['type'] = 'variable';
        $payload['attributes'] = $parentAttributes;
      }
    }

    // --- 4) Upsert (mit Preflight) --------------------------------------
    Log::info('ProductExportOrchestrator: upserting product', [
      'product_id'      => $product->id,
      'woo_product_id'  => $product->woo_product_id,
      'type'            => $type,
      'has_variations'  => $type === 'variable',
      'parent_sku_used' => $payload['sku'] ?? null,
    ]);

    $result = $this->upsert->upsertProduct($product, $payload, $failHard);

    Log::info('ProductExportOrchestrator: upsert result', [
      'product_id' => $product->id,
      'result'     => $result,
    ]);

    return $result;
  }
}
