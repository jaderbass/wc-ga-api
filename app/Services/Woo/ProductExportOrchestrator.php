<?php

namespace App\Services\Woo;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Class ProductExportOrchestrator
 *
 * Zweck:
 * - Zentraler Ablauf zur Erstellung/Aktualisierung von WooCommerce-Produkten und -Varianten.
 * - Bindet den WooParentResolver ein (SKU-first-Strategie mit Fallbacks nur ohne SKU).
 * - Entscheidet anhand des Resolve-Ergebnisses zwischen Update und Neuanlage.
 *
 * Strenger Variations-Flow:
 * - Für Varianten wird NIE auf "create_product" (Simple) fallbacked.
 * - Entweder Variation unter bestehendem/neu erzeugtem Parent – oder action="error".
 *
 * Zusätzliche Logik:
 * - Vor dem Anlegen eines Parents werden dessen Attribute auf **Taxonomie-IDs** umgestellt
 *   und die Options aus den tatsächlichen Variantenwerten befüllt (z. B. ["Rot","Blau"]).
 */
class ProductExportOrchestrator
{
  protected WooParentResolver $resolver;
  protected WooRepositoryInterface $repo;

  public function __construct(WooParentResolver $resolver, WooRepositoryInterface $repo)
  {
    $this->resolver = $resolver;
    $this->repo     = $repo;
  }

  /**
   * Führt den Export/Sync eines einzelnen Kandidaten durch.
   *
   * @param  array $candidate
   * @return array
   */
  public function exportOne(array $candidate): array
  {
    $attrs   = (array) Arr::get($candidate, 'attributes', []);
    $isVar   = (bool) Arr::get($candidate, 'is_variant', !empty($attrs));
    $payload = (array) Arr::get($candidate, 'payload', []);

    Log::debug('ProductExportOrchestrator: Start', [
      'has_sku'    => Arr::has($candidate, 'sku'),
      'is_variant' => $isVar,
    ]);

    // 1) Bestehenden Eintrag ermitteln (SKU-first)
    $resolved = $this->resolver->resolve($candidate);

    Log::debug('ProductExportOrchestrator: Resolve result', [
      'found'        => $resolved['found'],
      'type'         => $resolved['type'],
      'product_id'   => $resolved['product_id'] ?? null,
      'variation_id' => $resolved['variation_id'] ?? null,
      'matched_by'   => $resolved['matched_by'] ?? null,
      'notes'        => $resolved['notes'] ?? [],
    ]);

    // 2) GEFUNDEN → Update/Creates
    if ($resolved['found'] === true) {
      // 2a) Existierende Variation → Update Variation
      if ($resolved['type'] === 'variation' && !empty($resolved['variation_id']) && !empty($resolved['product_id'])) {
        $result = $this->safeRepo('updateVariation', (int)$resolved['product_id'], (int)$resolved['variation_id'], $payload);

        return [
          'action'       => 'update_variation',
          'product_id'   => (int)$resolved['product_id'],
          'variation_id' => (int)$resolved['variation_id'],
          'matched_by'   => $resolved['matched_by'],
          'notes'        => $resolved['notes'],
          'result'       => $result,
        ];
      }

      // 2b) Parent/Simple existiert
      if ($isVar) {
        // Keine passende Variation gefunden → Variation unter Parent anlegen
        $parentId = (int) ($resolved['product_id'] ?? $resolved['variation_id'] ?? 0);

        if ($parentId <= 0) {
          // Streng: Kein Parent → kein Simple-Fallback!
          Log::error('ProductExportOrchestrator: Parent-ID für Variation fehlt, breche ab (kein Simple-Fallback).', [
            'resolved' => $resolved,
          ]);
          return [
            'action'       => 'error',
            'product_id'   => null,
            'variation_id' => null,
            'matched_by'   => $resolved['matched_by'] ?? null,
            'notes'        => array_merge($resolved['notes'] ?? [], [
              'Parent-ID fehlte bei is_variant=true – Vorgang abgebrochen, um Simple-Doppelanlage zu vermeiden.',
            ]),
            'result'       => null,
          ];
        }

        Log::debug('ProductExportOrchestrator: createVariation unter vorhandenem Parent', ['parent_id' => $parentId]);
        $result = $this->safeRepo('createVariation', $parentId, $payload);

        return [
          'action'       => 'create_variation',
          'product_id'   => $parentId,
          'variation_id' => Arr::get($result, 'id'),
          'matched_by'   => $resolved['matched_by'],
          'notes'        => array_merge($resolved['notes'], ['Variation neu angelegt (unter vorhandenem Parent).']),
          'result'       => $result,
        ];
      }

      // 2c) Kein Variantenszenario → Update Parent/Simple
      $targetId = (int) ($resolved['product_id'] ?? $resolved['variation_id'] ?? 0);
      $result   = $this->safeRepo('updateProduct', $targetId, $payload);

      return [
        'action'       => 'update_product',
        'product_id'   => $targetId,
        'variation_id' => null,
        'matched_by'   => $resolved['matched_by'],
        'notes'        => $resolved['notes'],
        'result'       => $result,
      ];
    }

    // 3) NICHT GEFUNDEN → Create-Pfade
    if ($isVar) {
      // Parent-Anlage ist zwingend – vorher Attribute normalisieren (Taxonomie-IDs + Options aus Variationswerten)
      $parentPayload = $this->normalizeParentPayloadAttributes(
        (array) Arr::get($candidate, 'parent_payload', []),
        (array) Arr::get($candidate, 'attributes', [])
      );

      if (empty($parentPayload)) {
        Log::error('ProductExportOrchestrator: parent_payload fehlt bei is_variant=true – breche ab (kein Simple-Fallback).');
        return [
          'action'       => 'error',
          'product_id'   => null,
          'variation_id' => null,
          'matched_by'   => null,
          'notes'        => array_merge($resolved['notes'] ?? [], [
            'Für Varianten ist parent_payload zwingend – simple Produktanlage wurde verhindert.',
          ]),
          'result'       => null,
        ];
      }

      Log::debug('ProductExportOrchestrator: Parent nicht gefunden – lege Parent neu an (variable, Taxonomie-Attribute).', [
        'parent_payload_keys' => array_keys($parentPayload),
      ]);
      $parent   = $this->safeRepo('createProduct', $parentPayload);
      $parentId = (int) Arr::get($parent, 'id', 0);

      Log::debug('ProductExportOrchestrator: Ergebnis createProduct (Parent)', [
        'response_has_id' => $parentId > 0,
        'parent_id'       => $parentId,
      ]);

      if ($parentId <= 0) {
        Log::error('ProductExportOrchestrator: Parent konnte nicht angelegt werden – breche ab (kein Simple-Fallback).', [
          'parent_response' => $parent,
        ]);
        return [
          'action'       => 'error',
          'product_id'   => null,
          'variation_id' => null,
          'matched_by'   => null,
          'notes'        => array_merge($resolved['notes'] ?? [], [
            'Parent konnte nicht angelegt werden – Vorgang abgebrochen, um Simple-Doppelanlage zu vermeiden.',
          ]),
          'result'       => $parent,
        ];
      }

      Log::debug('ProductExportOrchestrator: lege Variation unter neuem Parent an', ['parent_id' => $parentId]);
      $variation = $this->safeRepo('createVariation', $parentId, $payload);

      return [
        'action'       => 'create_variation',
        'product_id'   => $parentId,
        'variation_id' => Arr::get($variation, 'id'),
        'matched_by'   => null,
        'notes'        => array_merge($resolved['notes'] ?? [], ['Parent (variable) neu angelegt, anschließend Variation erstellt.']),
        'result'       => $variation,
      ];
    }

    // 3b) Simple/Parent neu anlegen (nur wenn kein Variantenszenario)
    $result = $this->safeRepo('createProduct', $payload);

    return [
      'action'       => 'create_product',
      'product_id'   => Arr::get($result, 'id'),
      'variation_id' => null,
      'matched_by'   => null,
      'notes'        => $resolved['notes'] ?? [],
      'result'       => $result,
    ];
  }

  /**
   * Wandelt parent_payload['attributes'] auf Taxonomie-IDs um und füllt die Options
   * aus den tatsächlichen Variationswerten.
   *
   * Erwartete Config:
   *   config('woo.mapping.variation_attribute_taxonomies') => ['color' => 'pa_color', 'size' => 'pa_size', ...]
   *
   * Erwartete Repo-Erweiterung (optional, wird geprüft):
   *   getAttributeIdBySlug(string $slug): ?int
   *
   * @param  array $parentPayload
   * @param  array $variationAttributes  z. B. ['color' => 'Red', 'size' => 'L']
   * @return array
   */
  protected function normalizeParentPayloadAttributes(array $parentPayload, array $variationAttributes): array
  {
    $slugs = (array) config('woo.mapping.variation_attribute_taxonomies', [
      'color' => 'pa_color',
      'size'  => 'pa_size',
    ]);

    // Map lokale Keys → Slugs (nur vorhandene Variations-Keys berücksichtigen)
    $wanted = [];
    foreach ($variationAttributes as $k => $val) {
      if ($val === null || $val === '') {
        continue;
      }
      $slug = $slugs[$k] ?? null;
      if ($slug) {
        $wanted[$slug][] = (string) $val;
      }
    }

    if (empty($wanted)) {
      // Nichts zu tun
      return $parentPayload;
    }

    $normalized = [];
    foreach ($wanted as $slug => $values) {
      $id = $this->getAttributeIdBySlugIfPossible($slug); // null, wenn Repo es nicht anbietet
      $options = array_values(array_unique(array_map('strval', $values)));

      if ($id) {
        // Taxonomie-Attribut per ID
        $normalized[] = [
          'id'        => $id,
          'visible'   => true,
          'variation' => true,
          'options'   => $options, // Term-Namen; Woo mappt sie auf bestehende oder legt an
        ];
      } else {
        // Fallback: per Name (kann als Custom-Attribut enden – weniger ideal)
        $normalized[] = [
          'name'      => $slug,
          'visible'   => true,
          'variation' => true,
          'options'   => $options,
        ];
      }
    }

    $parentPayload['type']       = 'variable';
    $parentPayload['attributes'] = $normalized;

    Log::debug('ProductExportOrchestrator: normalized parent attributes', [
      'attrs' => $normalized,
    ]);

    return $parentPayload;
  }

  /**
   * Holt die Attribut-ID zu einem Taxonomie-Slug, falls das Repo das kann.
   */
  protected function getAttributeIdBySlugIfPossible(string $slug): ?int
  {
    if (method_exists($this->repo, 'getAttributeIdBySlug')) {
      try {
        $id = $this->repo->getAttributeIdBySlug($slug);
        return $id ? (int) $id : null;
      } catch (\Throwable $e) {
        Log::warning('ProductExportOrchestrator: getAttributeIdBySlug failed', [
          'slug'    => $slug,
          'message' => $e->getMessage(),
        ]);
      }
    } else {
      Log::debug('ProductExportOrchestrator: repo has no getAttributeIdBySlug, using name fallback', ['slug' => $slug]);
    }
    return null;
  }

  /**
   * Kapselt Repo-Aufrufe mit Logging bei fehlender Methode/Exceptions.
   *
   * @param  string $method
   * @param  mixed  ...$args
   * @return array|null
   */
  protected function safeRepo(string $method, ...$args): ?array
  {
    if (!method_exists($this->repo, $method)) {
      Log::warning('ProductExportOrchestrator: Repository-Methode fehlt', ['method' => $method]);
      return null;
    }

    try {
      $res = $this->repo->{$method}(...$args);
      Log::debug('ProductExportOrchestrator: Repo call ok', ['method' => $method]);
      return is_array($res) ? $res : (is_object($res) ? (array) $res : ['value' => $res]);
    } catch (\Throwable $e) {
      Log::warning('ProductExportOrchestrator: Repository-Aufruf fehlgeschlagen', [
        'method'  => $method,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }
}
