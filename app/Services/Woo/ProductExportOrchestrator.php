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
 * Voraussetzungen:
 * - Ein Repository/Client ($repo) kapselt die konkreten Woo-API-Operationen.
 *   Erwartete Methoden (Bezeichnungen beispielhaft – ggf. an Dein Projekt anpassen):
 *     - updateProduct(int $productId, array $payload): array
 *     - updateVariation(int $parentId, int $variationId, array $payload): array
 *     - createProduct(array $payload): array                 // Parent/Simple
 *     - createVariation(int $parentId, array $payload): array
 *
 * Eingabeformat ($candidate):
 * - [
 *     'sku'         => 'PETZL-A010EA00-RED-L',   // optional (SKU-first!)
 *     'ean'         => '3342540833561',          // optional
 *     'mpn'         => 'A010EA00',               // optional
 *     'brand'       => 'Petzl',                  // optional
 *     'attributes'  => ['color' => 'RED', 'size' => 'L'], // optional
 *     'is_variant'  => true|false,               // optional (Fallback: true, wenn attributes nicht leer)
 *     'payload'     => [...],                    // vollständiger Woo-Payload (Titel, Preis, Meta etc.)
 *     'parent_payload' => [...],                 // falls Variante neu angelegt werden muss, Parent-Daten hier
 *   ]
 *
 * Rückgabe:
 * - [
 *     'action'      => 'update_product'|'update_variation'|'create_product'|'create_variation',
 *     'product_id'  => int|null,
 *     'variation_id'=> int|null,
 *     'matched_by'  => 'sku'|'ean'|'mpn'|'composite'|null,
 *     'notes'       => string[],
 *     'result'      => array|null   // API-Antwort
 *   ]
 *
 * Logging:
 * - Ausführliche Debug-Logs mit Schrittmarkern.
 *
 * @package App\Services\Woo
 */
class ProductExportOrchestrator
{
  /** @var WooParentResolver */
  protected WooParentResolver $resolver;

  /** @var object */
  protected $repo;

  /**
   * @param  WooParentResolver $resolver
   * @param  object            $repo      Repository/Client mit Woo-spezifischen API-Methoden
   */
  public function __construct(WooParentResolver $resolver, object $repo)
  {
    $this->resolver = $resolver;
    $this->repo     = $repo;
  }

  /**
   * Führt den Export/Sync eines einzelnen Kandidaten durch.
   *
   * Ablauf:
   * 1) Resolve auf bestehenden Woo-Eintrag (SKU-first).
   * 2) Je nach Ergebnis Update oder Create (Produkt/Variante).
   *
   * @param  array $candidate  Siehe Klassendoku für das erwartete Format.
   * @return array             Struktur mit Aktion, IDs, Notizen und API-Ergebnis.
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

    // 2) Entscheidung
    if ($resolved['found'] === true) {
      // 2a) Update-Pfade
      if ($resolved['type'] === 'variation' && !empty($resolved['variation_id']) && !empty($resolved['product_id'])) {
        // Variation existiert → Update Variation
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

      // Parent/Simple existiert
      if ($isVar) {
        // Der Resolver hat keine passende Variation gefunden → Variation unter Parent anlegen
        $parentId = (int) ($resolved['product_id'] ?? $resolved['variation_id'] ?? 0);
        if ($parentId <= 0) {
          // Edge Case: sollte nicht vorkommen, aber zur Sicherheit
          Log::warning('ProductExportOrchestrator: Parent-ID für Variantenerstellung fehlt – fallback: create_product');
          $result = $this->safeRepo('createProduct', $payload);
          return [
            'action'       => 'create_product',
            'product_id'   => Arr::get($result, 'id'),
            'variation_id' => null,
            'matched_by'   => $resolved['matched_by'],
            'notes'        => array_merge($resolved['notes'], ['Parent-ID fehlte, Produkt neu angelegt.']),
            'result'       => $result,
          ];
        }

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

      // Kein Variantenszenario → Update Parent/Simple
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

    // 2b) Create-Pfade (kein bestehender Eintrag gefunden)
    if ($isVar) {
      // Variante ohne vorhandenen Parent: zuerst Parent erzeugen (falls parent_payload vorhanden), dann Variation
      $parentPayload = (array) Arr::get($candidate, 'parent_payload', []);
      $parentId      = null;

      if (!empty($parentPayload)) {
        Log::debug('ProductExportOrchestrator: Parent nicht gefunden – lege Parent neu an (parent_payload vorhanden).');
        $parent = $this->safeRepo('createProduct', $parentPayload);
        $parentId = (int) Arr::get($parent, 'id');
      }

      if ($parentId) {
        $variation = $this->safeRepo('createVariation', $parentId, $payload);

        return [
          'action'       => 'create_variation',
          'product_id'   => $parentId,
          'variation_id' => Arr::get($variation, 'id'),
          'matched_by'   => null,
          'notes'        => array_merge($resolved['notes'], ['Parent neu angelegt, anschließend Variation erstellt.']),
          'result'       => $variation,
        ];
      }

      // Fallback: kein parent_payload → Variante als eigenes (Simple/Parent) Produkt anlegen
      Log::warning('ProductExportOrchestrator: Kein parent_payload vorhanden – lege Produkt als Simple/Parent an.');
      $result = $this->safeRepo('createProduct', $payload);

      return [
        'action'       => 'create_product',
        'product_id'   => Arr::get($result, 'id'),
        'variation_id' => null,
        'matched_by'   => null,
        'notes'        => array_merge($resolved['notes'], ['Variation ohne Parent-Payload – Produkt als Simple/Parent angelegt.']),
        'result'       => $result,
      ];
    }

    // Simple/Parent neu anlegen
    $result = $this->safeRepo('createProduct', $payload);

    return [
      'action'       => 'create_product',
      'product_id'   => Arr::get($result, 'id'),
      'variation_id' => null,
      'matched_by'   => null,
      'notes'        => $resolved['notes'],
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
