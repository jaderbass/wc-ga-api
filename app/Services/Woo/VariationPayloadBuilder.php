<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class VariationPayloadBuilder
{
  private ?PendingRequest $http = null;
  private array $attrIdCache = []; // ['pa_color' => 3, ...]

  /**
   * Haupt-Einstieg: baut das Woo-Payload für eine einzelne Variante.
   * - nutzt WooAttributeResolver (falls vorhanden), sonst Fallback-Mapping
   * - keine Preisfelder
   */
  public function build(Product $parent, ProductVariation $variation): array
  {
    // HTTP-Client lazy initialisieren (gleiche Basis wie im UpsertService)
    $this->initHttp();

    $attributes = $this->resolveAttributes($parent, $variation);

    // --- Normalize attribute slugs to Woo global format (pa_*) ---
    // Wir arbeiten mit Attribut-Objekten im Format ['name' => ..., 'option' => ...].
    // Falls 'name' nicht mit 'pa_' beginnt, präfixen wir es.
    foreach ($attributes as &$attr) {
      if (!is_array($attr) || !isset($attr['name'])) {
        continue;
      }
      $name = (string) $attr['name'];
      if ($name !== '' && !str_starts_with($name, 'pa_')) {
        // Doppel-Underscore vermeiden (z. B. "__size") und sauber präfixen
        $attr['name'] = 'pa_' . ltrim($name, '_');
      }
      // 🔁 NEU: auf deutsche Woo-Slugs mappen (pa_color -> pa_farbe, pa_size -> pa_groessen)
      [$mappedSlug, $mappedLabel] = $this->mapWooAttributeSlugGerman($attr['name']);
      $attr['name']  = $mappedSlug;   // z. B. pa_farbe
      $attr['_lbl']  = $mappedLabel;  // z. B. "Farbe" (nur intern für ensureAttributeId)
    }
    unset($attr);

    // Debug-Log, um die finalen Attribute zu verifizieren
    Log::debug('VariationPayloadBuilder: normalized attribute slugs', [
      'product_id' => $parent->id,
      'attributes' => $attributes,
    ]);

    // --- WICHTIG: Slug-Attribute -> ID-Attribute (Woo erwartet IDs bei globalen Attributen) ---
    $final = [];
    foreach ($attributes as $a) {
      if (!is_array($a)) continue;
      $slug   = (string) ($a['name']   ?? '');
      $option = (string) ($a['option'] ?? '');
      if ($slug === '' || $option === '') continue;
      // 🇩🇪 Attribut-ID sicherstellen (deutsche Slugs/Labels)
      $labelFromAttr = (string)($a['_lbl'] ?? '');
      [$slug, $label] = $this->mapWooAttributeSlugGerman($slug); // safety
      $id = $this->ensureAttributeId($slug, $labelFromAttr ?: $label);
      // Term sicherstellen (legt den Wert ggf. an)
      $opt = $this->normalizeTermOption($option);
      $this->ensureTerm($id, $opt);
      if ($id) {
        $final[] = ['id' => $id, 'option' => $opt];
      } else {
        // Fallback: wenn keine ID ermittelbar, altes Format beibehalten
        $final[] = ['name' => $slug, 'option' => $option];
        Log::warning('VariationPayloadBuilder: attribute slug not found on Woo', [
          'slug' => $slug,
          'product_id' => $parent->id,
          'variation_id' => $variation->id
        ]);
      }
    }
    $attributes = $final;

    Log::debug('VariationPayloadBuilder: resolved attribute IDs', [
      'product_id' => $parent->id,
      'variation_id' => $variation->id,
      'attributes' => $attributes,
    ]);

    if (empty($attributes)) {
      Log::warning('VariationPayloadBuilder: no attributes for variation', [
        'variation_id' => $variation->id,
        'sku'          => $variation->sku,
        'product_id'   => $parent->id,
      ]);
    }

    $payload = [
      'sku'        => $variation->sku ?: null,
      'attributes' => array_values($attributes),
    ];

    // Lager / Bestand
    if ($variation->manage_stock) {
      $payload['manage_stock'] = true;
      if (property_exists($variation, 'stock_quantity') && $variation->stock_quantity !== null) {
        $payload['stock_quantity'] = (int) $variation->stock_quantity;
      }
    }

    if (!empty($variation->stock_status)) {
      $mapped = $this->mapStockStatus($variation->stock_status);
      if ($mapped !== null) {
        $payload['stock_status'] = $mapped; // 'instock'|'outofstock'|'onbackorder'
      }
    }

    if (!empty($variation->backorders)) {
      $payload['backorders'] = $variation->backorders;
    }


    // Dimensionen und Gewicht
    $dims = [
      'weight' => $variation->weight_g ? (string) ($variation->weight_g / 1000) : null,
      'length' => $variation->length_mm ? (string) ($variation->length_mm / 10) : null,
      'width'  => $variation->width_mm  ? (string) ($variation->width_mm / 10) : null,
      'height' => $variation->height_mm ? (string) ($variation->height_mm / 10) : null,
    ];
    $dims = array_filter($dims, fn($v) => $v !== null && $v !== '');
    if (!empty($dims)) {
      $payload['dimensions'] = $dims;
    }

    Log::debug('VariationPayloadBuilder: single payload built', [
      'product_id'   => $parent->id,
      'variation_id' => $variation->id,
      'sku'          => $variation->sku,
      'attributes'   => $payload['attributes'],
    ]);

    return $payload;
  }

  /**
   * Liefert die Woo-Attribute einer Variante.
   * - bevorzugt WooAttributeResolver, sonst Fallback-Feldmapping
   */
  private function resolveAttributes(\App\Models\Product $parent, \App\Models\ProductVariation $variation): array
  {
    // 1) Resolver bevorzugen, wenn vorhanden
    if (app()->bound(WooAttributeResolver::class)) {
      try {
        $resolver = app(WooAttributeResolver::class);
        foreach (['attributesForVariation', 'buildVariationAttributes', 'resolveVariationAttributes'] as $method) {
          if (method_exists($resolver, $method)) {
            $attrs = $resolver->{$method}($parent, $variation);
            if (is_array($attrs) && !empty($attrs)) {
              return array_values($attrs);
            }
          }
        }
      } catch (\Throwable $e) {
        Log::warning('WooAttributeResolver failed for variation', [
          'variation_id' => $variation->id,
          'error'        => $e->getMessage(),
        ]);
      }
    }

    // 2) DB-basierter Fallback über Pivot:
    // piv (product_variation_attribute_value) -> pav (product_attribute_values) -> pa (product_attributes)
    $rows = \Illuminate\Support\Facades\DB::table('product_variation_attribute_value as piv')
      ->join('product_attribute_values as pav', 'pav.id', '=', 'piv.product_attribute_value_id')
      ->join('product_attributes as pa', 'pa.id', '=', 'pav.attribute_id')
      ->where('piv.product_variation_id', $variation->id)
      ->select([
        'pa.slug as attr_slug',         // z. B. 'pa_size', 'pa_color' (oder projekt-spezifische Slugs)
        'pav.value as option_value',    // sichtbarer Optionswert (z. B. 'M', 'Blau')
      ])
      ->get();

    $attrs = [];
    foreach ($rows as $r) {
      $slug = (string) ($r->attr_slug ?? '');
      $val  = (string) ($r->option_value ?? '');
      if ($slug === '' || $val === '') {
        continue;
      }
      $attrs[] = [
        'name'   => $slug,
        'option' => $val,
      ];
    }

    return $attrs;
  }

  private function initHttp(): void
  {
    if ($this->http) return;
    $base = rtrim((string) config('woo.api.base_url'), '/');
    $ver  = (string) config('woo.default_api_version', 'wc/v3');
    $this->http = Http::baseUrl($base . '/wp-json/' . $ver)
      ->withBasicAuth((string) config('woo.api.key'), (string) config('woo.api.secret'))
      ->acceptJson()->asJson()->retry(3, 200);
  }

  /**
   * Mappt 'pa_color' -> 'Color', 'pa_size' -> 'Size', sonst lesbarer Fallback.
   */
  private function mapAttributeLabel(string $slug): string
  {
    return match ($slug) {
      'pa_color' => 'Color',
      'pa_size'  => 'Size',
      default    => ucfirst(ltrim($slug, '_')),
    };
  }
  
  /**
   * 🇩🇪 Mappt eingehende (ggf. englische) Slugs auf deutsche Woo-Slugs + Label.
   * Beispiele:
   *  - 'pa_color' / 'color'   -> ['pa_farbe',   'Farbe']
   *  - 'pa_size'  / 'size'    -> ['pa_groessen','Größen']
   *  - sonst: passt unverändert durch, Label = ucfirst(slug ohne pa_/underscores)
   *
   * @param string $slug Eingehender (evtl. bereits 'pa_'-präfigierter) Slug
   * @return array{0:string,1:string} [mappedSlug, mappedLabel]
   */
  private function mapWooAttributeSlugGerman(string $slug): array
  {
    $s = ltrim($slug, '_');
    $s = str_starts_with($s, 'pa_') ? $s : ('pa_' . $s);
    return match ($s) {
      'pa_color', 'pa_colour' => ['pa_farbe', 'Farbe'],
      'pa_size'               => ['pa_groessen', 'Größen'],
      default => [$s, ucfirst(str_replace(['pa_','_'], ['', ' '], $s))]
    };
  }

  /**
   * Sucht/erstellt das globale Attribut (deutscher Slug).
   * Akzeptiert sowohl 'pa_farbe' als auch 'farbe' etc.
   *
   * @param string $slug   Erwarteter Slug, z. B. 'pa_farbe'
   * @param string $label  Anzeigename, z. B. 'Farbe'
   * @return int           Woo-Attribut-ID
   */
  private function ensureAttributeId(string $slug, string $label): int
  {
    if (isset($this->attrIdCache[$slug])) return $this->attrIdCache[$slug];
    $resp = $this->http->get('products/attributes');
    $list = $resp->successful() ? ($resp->json() ?? []) : [];

    // sowohl pa_* als auch ohne Präfix akzeptieren
    $candidates = [$slug];
    $plain = str_starts_with($slug, 'pa_') ? substr($slug, 3) : $slug;
    $candidates[] = $plain;

    foreach ($list as $a) {
      $found = (string) ($a['slug'] ?? '');
      if (in_array($found, $candidates, true)) {
        $id = (int) ($a['id'] ?? 0);
        return $this->attrIdCache[$slug] = $id;
      }
    }

    // nicht vorhanden -> korrekt mit deutschem Slug anlegen
    $fixedSlug = str_starts_with($slug, 'pa_') ? $slug : ('pa_' . $slug);
    $create = $this->http->post('products/attributes', [
      'name'         => $label,
      'slug'         => $fixedSlug,
      'type'         => 'select',
      'order_by'     => 'menu_order',
      'has_archives' => false,
    ]);
    if (!$create->successful()) {
      throw new \RuntimeException("Failed to create attribute '{$fixedSlug}': ".$create->body());
    }
    $id = (int) ($create->json()['id'] ?? 0);
    Log::info('woo_attribute_created', ['slug'=>$fixedSlug,'id'=>$id]);
    return $this->attrIdCache[$slug] = $id;
  }

  /**
   * Stellt sicher, dass ein Term unter einem Attribut existiert (legt ihn ggf. an).
   *
   * @param int    $attributeId
   * @param string $nameOrSlug
   * @return void
   */
  private function ensureTerm(int $attributeId, string $nameOrSlug): void
  {
    $list = $this->http->get("products/attributes/{$attributeId}/terms");
    $terms = $list->successful() ? ($list->json() ?? []) : [];
    foreach ($terms as $t) {
      if ((string)($t['name'] ?? '') === $nameOrSlug || (string)($t['slug'] ?? '') === $nameOrSlug) return;
    }
    $resp = $this->http->post("products/attributes/{$attributeId}/terms", [
      'name' => $nameOrSlug,
      'slug' => $nameOrSlug,
    ]);
    if ($resp->successful()) {
      Log::info('woo_term_created', ['attribute_id'=>$attributeId,'term'=>$nameOrSlug]);
    } else {
      Log::warning('woo_term_create_failed', ['attribute_id'=>$attributeId,'term'=>$nameOrSlug,'body'=>$resp->body()]);
    }
  }

  /**
   * Lässt die Option im Zweifel so wie sie ist. (Optional könntest du hier auf Slug-Form normalisieren.)
   */
  private function normalizeTermOption(string $val): string
  {
    $s = trim($val);
    // if you prefer slug style, uncomment:
    // $s = mb_strtolower($s);
    // $s = str_replace([' / ', '/', ' & ', ' + '], ['-', '-', '-', '-'], $s);
    // $s = preg_replace('/\s+/', '-', $s);
    return $s;
  }

  /**
   * (optional) Mappt lokale Stock-Status-Werte auf Woo-REST-kompatible Werte.
   * Aufruf: beim Payload-Bau vor dem Setzen von 'stock_status' verwenden.
   */
  private function mapStockStatus(?string $status): ?string
  {
    if ($status === null || $status === '') return null;

    $s = strtolower(str_replace([' ', '-'], '_', $status));
    return match ($s) {
      'in_stock', 'instock'         => 'instock',
      'out_of_stock', 'outofstock'  => 'outofstock',
      'on_backorder', 'backorder'   => 'onbackorder',
      default                       => null, // Unbekannt → nicht senden
    };
  }


  public function buildForVariation(\App\Models\Product $parent, \App\Models\ProductVariation $variation): array
  {
    // Alias für ältere Aufrufer – delegiert auf build()
    return $this->build($parent, $variation);
  }
}
