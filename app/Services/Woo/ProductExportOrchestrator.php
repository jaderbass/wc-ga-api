<?php

namespace App\Services\Woo;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use function config;

/**
 * ProductExportOrchestrator
 *
 * Exportiert/aktualisiert Woo-Hauptprodukte mit:
 * - strenger SKU-Regel (variable Parent: KEINE SKU; simple: konfigurierbare Priorität)
 * - globalem Attribut "EAN" (pa_ean) auf Produktebene → landet in Woo-CSV als
 *   "Attribute Value (pa_ean)".
 * - OHNE Preise.
 *
 * Konfiguration (config/woo.php, relevante Keys):
 *
 * 'mapping' => [
 *   'identifiers' => [
 *     'product' => [
 *       'sku'  => 'sku',
 *       'mpn'  => 'product_number',
 *       'ean'  => 'ean',
 *       'gtin' => 'gtin',
 *     ],
 *   ],
 *   'sku_rules' => [
 *     'variable_parent_has_sku'        => false,
 *     'simple_parent_sku_priority'     => ['sku', 'product_number', 'ean', 'gtin'], // Priorität
 *   ],
 *   'attributes' => [
 *     'ean' => [
 *       'enabled'  => true,
 *       'slug'     => 'pa_ean',   // globales Attribut in Woo (muss existieren)
 *       'label'    => 'EAN',      // rein informativ
 *       'source'   => ['ean', 'gtin'], // Reihenfolge der Quellen im Product-Modell
 *       'visible'  => true,
 *       'variation'=> false,
 *     ],
 *   ],
 * ]
 *
 * Hinweise:
 * - Für das Attribut 'pa_ean' sollte im Woo-Backend ein globales Produktattribut existieren.
 * - Bei variablen Eltern setzen wir bewusst KEINE SKU.
 *
 * @author  JAderBass
 * @since   2025-09-24
 */
class ProductExportOrchestrator
{
  public function __construct(
    protected ProductUpsertService $upsert,
  ) {}

  /**
   * Synchronisiert genau ein Hauptprodukt (Create/Update, mit SKU-Preflight).
   *
   * @param  Product $product
   * @param  bool    $failHard
   * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
   */
  public function syncSingle(Product $product, bool $failHard = false): array
  {
    // --- Produkttyp bestimmen (robust) ---------------------------------------
    $hasVariations = method_exists($product, 'variations') ? $product->variations()->exists() : false;

    $type = (string) $product->getAttribute('product_type');
    if (!in_array($type, ['simple', 'variable'], true)) {
      $type = $hasVariations ? 'variable' : 'simple';
    }

    // --- Basis-Payload (OHNE Preise) -----------------------------------------
    $idForFallback = $product->getKey();
    $name          = (string) ($product->getAttribute('product_name') ?? $product->getAttribute('name') ?? "Product {$idForFallback}");
    $slug          = (string) ($product->getAttribute('slug') ?? '');
    $description   = (string) ($product->getAttribute('description') ?? '');
    $shortDesc     = (string) ($product->getAttribute('short_description') ?? '');

    $payload = [
      'type'              => $type,
      'name'              => $name,
      'slug'              => $slug,
      'status'            => 'publish',
      'description'       => $description,
      'short_description' => $shortDesc,
    ];

    // --- SKU-Entscheidung (nur für simple Parents) ---------------------------
    $sku = $this->decideParentSku($product, $type);
    if ($sku !== null) {
      $payload['sku'] = $sku;
    }

    // --- Bilder (optional) ---------------------------------------------------
    $img = $product->getAttribute('image_url');
    if (!empty($img)) {
      $payload['images'] = [
        ['src' => (string) $img],
      ];
    }

    // --- Globales Attribut EAN (pa_ean) --------------------------------------
    $eanAttr = $this->buildEanAttributeForProduct($product);
    if ($eanAttr !== null) {
      $payload['attributes'] = array_values(array_filter([
        ...($payload['attributes'] ?? []),
        $eanAttr,
      ]));
    }

    // --- Logging --------------------------------------------------------------
    Log::info('ProductExportOrchestrator: upserting product', [
      'product_id'         => $idForFallback,
      'woo_product_id'     => $product->getAttribute('woo_product_id'),
      'type'               => $type,
      'parent_sku_chosen'  => $sku,
      'has_ean_attribute'  => $eanAttr !== null,
      'ean_attribute_value' => $eanAttr['options'][0] ?? null,
    ]);

    // --- Upsert (Create/Update mit Preflight) --------------------------------
    $result = $this->upsert->upsertProduct($product, $payload, $failHard);

    Log::info('ProductExportOrchestrator: upsert result', [
      'product_id' => $idForFallback,
      'result'     => $result,
    ]);

    return $result;
  }

  /**
   * Wählt die Parent-SKU gemäß Regeln aus.
   * - variable: niemals eine SKU (gemäß config)
   * - simple: Priorität aus config('woo.mapping.sku_rules.simple_parent_sku_priority')
   */
  protected function decideParentSku(Product $product, string $type): ?string
  {
    $rules = (array) config('woo.mapping.sku_rules', []);
    $ident = (array) config('woo.mapping.identifiers.product', []);

    $map = [
      'sku'            => $ident['sku']  ?? 'sku',
      'product_number' => $ident['mpn']  ?? 'product_number',
      'ean'            => $ident['ean']  ?? 'ean',
      'gtin'           => $ident['gtin'] ?? 'gtin',
    ];

    if ($type === 'variable') {
      $allow = (bool) ($rules['variable_parent_has_sku'] ?? false);
      if (!$allow) {
        return null;
      }
      // Wenn doch erlaubt, fällt es unten in die gleiche Prioritätslogik.
    }

    $priority = (array) ($rules['simple_parent_sku_priority'] ?? ['sku', 'product_number', 'ean', 'gtin']);
    foreach ($priority as $key) {
      $col = $map[$key] ?? null;
      $val = $col ? $product->getAttribute($col) : null;
      if (!empty($val)) {
        return (string) $val;
      }
    }

    return null;
  }

  /**
   * Baut das globale Attribut "EAN" (pa_ean) für das Hauptprodukt,
   * wenn in der Konfiguration aktiviert und ein Wert vorhanden ist.
   *
   * Rückgabe: Woo-Attribut-Array oder null.
   */
  protected function buildEanAttributeForProduct(Product $product): ?array
  {
    $cfg = (array) config('woo.mapping.attributes.ean', []);
    if (empty($cfg['enabled'])) {
      return null;
    }

    $slug     = (string) ($cfg['slug']  ?? 'pa_ean'); // globaler Attribut-Slug
    $visible  = (bool)   ($cfg['visible'] ?? true);
    $isVarDef = (bool)   ($cfg['variation'] ?? false);

    // Quellen für den EAN-Wert (z. B. ['ean','gtin'])
    $ident     = (array) config('woo.mapping.identifiers.product', []);
    $sourceKeys = (array) ($cfg['source'] ?? ['ean', 'gtin']);
    $srcMap = [
      'ean'  => $ident['ean']  ?? 'ean',
      'gtin' => $ident['gtin'] ?? 'gtin',
    ];

    $value = null;
    foreach ($sourceKeys as $key) {
      $col = $srcMap[$key] ?? null;
      $val = $col ? $product->getAttribute($col) : null;
      if (!empty($val)) {
        $value = (string) $val;
        break;
      }
    }

    if ($value === null) {
      return null;
    }

    // Für globale Attribute sollte 'name' der Slug 'pa_*' sein,
    // damit Woo es dem globalen Attribut zuordnen kann.
    return [
      'name'      => $slug,       // z. B. 'pa_ean'
      'visible'   => $visible,
      'variation' => $isVarDef,   // EAN ist normalerweise kein variationsbestimmendes Attribut
      'options'   => [$value],
    ];
  }
}
