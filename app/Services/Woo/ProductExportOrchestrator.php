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
 * WICHTIG (Strenger Variations-Flow):
 * - Für Varianten wird NIE auf "create_product" (Simple) fallbacked.
 * - Entweder wird eine Variation unter bestehendem/neu erzeugtem Parent erstellt/aktualisiert,
 *   oder der Vorgang endet mit action="error".
 *
 * Erwartete Repo-Methoden:
 *   - updateProduct(int $productId, array $payload): array|null
 *   - updateVariation(int $parentId, int $variationId, array $payload): array|null
 *   - createProduct(array $payload): array|null
 *   - createVariation(int $parentId, array $payload): array|null
 *
 * @package App\Services\Woo
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

    // 2) GEFUNDEN → Update oder angepasste Create-Pfade
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
          // Strenger: Kein Parent → kein Simple-Fallback!
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
      // Variante ohne vorhandenen Parent: Parent aus parent_payload erzwingen
      $parentPayload = (array) Arr::get($candidate, 'parent_payload', []);

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

      Log::debug('ProductExportOrchestrator: Parent nicht gefunden – lege Parent neu an (type=variable erwartet).', [
        'parent_payload_keys' => array_keys($parentPayload),
      ]);
      $parent = $this->safeRepo('createProduct', $parentPayload);
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
        'notes'        => array_merge($resolved['notes'] ?? [], ['Parent neu angelegt, anschließend Variation erstellt.']),
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
