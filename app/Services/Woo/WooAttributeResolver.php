<?php

namespace App\Services\Woo;

use App\Models\Shop;

class WooAttributeResolver
{
  protected WooClient $woo;
  /** @var array<string,array{id:int,name:string,slug:string}> */
  protected array $cache = [];

  /** Lokale → Woo-Mapping (alle Schreibweisen abdecken) */
  protected array $localMap = [
    'farbe'  => 'pa_color',
    'color'  => 'pa_color',
    'grosse' => 'pa_size',
    'groesse' => 'pa_size',
    'size'   => 'pa_size',
  ];

  public function __construct(Shop $shop)
  {
    $this->woo = new WooClient($shop);
  }

  /**
   * Liefert Woo-Attribut (id, name, slug) für lokalen Key (z. B. "farbe" / "size").
   * Gibt null zurück, wenn nicht gefunden.
   */
  public function resolve(string $localKey): ?array
  {
    $localKey = strtolower($localKey);
    $wantSlug = $this->localMap[$localKey] ?? null;
    if (!$wantSlug) return null;

    if (!empty($this->cache)) {
      return $this->bySlug($wantSlug);
    }

    // Alle Woo-Attribute laden und cachen
    $list = $this->woo->get('products/attributes'); // [{id,name,slug},...]
    foreach ($list as $attr) {
      $slug = $attr['slug'] ?? '';
      if ($slug) {
        $this->cache[$slug] = [
          'id'   => (int)$attr['id'],
          'name' => (string)$attr['name'],
          'slug' => (string)$slug,
        ];
      }
    }
    return $this->bySlug($wantSlug);
  }

  protected function bySlug(string $slug): ?array
  {
    return $this->cache[$slug] ?? null;
  }
}
