<?php

namespace App\Services\Woo;

use App\Support\IdentityNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Class WooParentResolver
 *
 * Zweck:
 * - Ermittelt für ein zu exportierendes Produkt/Variante, ob in WooCommerce bereits
 *   ein passender Datensatz existiert.
 * - **SKU ist primärer Match-Key (99,98% Eindeutigkeit laut Kunde).**
 * - EAN/MPN/Composite-SKU werden nur genutzt, wenn **keine SKU** vorhanden ist.
 * - Liefert strukturierte Resolve-Antwort (Parent-/Variation-IDs, matchedBy, normalisierte Keys).
 *
 * Erwartete Repository-Methoden (bereitgestellt durch WooApiRepository):
 * - findBySku(string $sku): ?array
 * - findByEan(string $ean): ?array
 * - findByMpn(string $mpn): ?array
 * - findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int
 * - getParentId(array $item): ?int
 * - getItemId(array $item): int
 * - getItemType(array $item): 'parent'|'simple'|'variation'
 *
 * Übergabeformat $candidate (Beispiel):
 * [
 *   'sku'        => 'PETZL-A010EA00-RED-L', // optional
 *   'ean'        => '3342540833561',        // optional
 *   'mpn'        => 'A010EA00',             // optional
 *   'brand'      => 'Petzl',                // optional (für Composite-SKU)
 *   'attributes' => ['color' => 'RED', 'size' => 'L'], // optional
 *   'is_variant' => true|false              // optional (Fallback: true, wenn attributes nicht leer)
 * ]
 *
 * Rückgabe:
 * [
 *   'found'        => bool,
 *   'type'         => 'parent'|'variation'|'simple'|'none',
 *   'product_id'   => int|null,
 *   'variation_id' => int|null,
 *   'matched_by'   => 'sku'|'ean'|'mpn'|'composite'|null,
 *   'normalized'   => ['sku'=>?string,'ean'=>?string,'mpn'=>?string,'composite'=>?string],
 *   'notes'        => string[]
 * ]
 *
 * Logging:
 * - Ausführliche Debug-Logs pro Matching-Schritt. Keine Backslashes vor Log::.
 *
 * @package App\Services\Woo
 */
class WooParentResolver
{
  /** @var object Repository mit den oben beschriebenen Methoden (typischerweise WooApiRepository) */
  protected $repo;

  /**
   * @param  object  $repo  Repository (WooApiRepository), welches die erwarteten Methoden anbietet.
   */
  public function __construct(object $repo)
  {
    $this->repo = $repo;
  }

  /**
   * Führt die Auflösung für ein Produkt/Variante durch (SKU-first).
   *
   * Strategie:
   * 1) Wenn SKU vorhanden: ausschließlich SKU-Match (kein Fallback).
   * 2) Wenn keine SKU: EAN → MPN → Composite-SKU.
   * 3) Bei Varianten: Falls Parent gefunden, versuche Variation anhand Attribute.
   *
   * @param  array $candidate
   * @return array
   */
  public function resolve(array $candidate): array
  {
    $notes = [];

    $normSku  = IdentityNormalizer::normalizeSku(Arr::get($candidate, 'sku'));
    $normEan  = IdentityNormalizer::normalizeEan(Arr::get($candidate, 'ean'));
    $normMpn  = IdentityNormalizer::normalizeMpn(Arr::get($candidate, 'mpn'));
    $brand    = Arr::get($candidate, 'brand');
    $attrs    = (array) Arr::get($candidate, 'attributes', []);
    $isVar    = (bool) Arr::get($candidate, 'is_variant', !empty($attrs));

    $composite = null;
    if (!$normSku) {
      $composite = IdentityNormalizer::buildCompositeSku($brand, $normMpn, $attrs);
    }

    $normalized = [
      'sku'       => $normSku,
      'ean'       => $normEan,
      'mpn'       => $normMpn,
      'composite' => $composite,
    ];

    Log::debug('WooParentResolver: start', [
      'candidate_has_sku' => (bool)$normSku,
      'normalized'        => $normalized,
      'is_variant'        => $isVar,
    ]);

    // 1) Primär: SKU (exklusiv)
    if ($normSku) {
      $found = $this->safeCall('findBySku', $normSku);
      if ($found) {
        $resolved = $this->finalizeResolve($found, 'sku', $attrs, $isVar, $notes);
        Log::debug('WooParentResolver: matched by SKU', $resolved);
        $resolved['normalized'] = $normalized;
        return $resolved;
      }
      $notes[] = "SKU nicht gefunden: {$normSku}";
      Log::debug('WooParentResolver: no match by SKU', ['sku' => $normSku]);

      // Wichtig: Bei vorhandener SKU **kein** Fallback (Kundenregel)
      return $this->notFound($normalized, $notes);
    }

    // 2) Fallbacks nur wenn SKU fehlt
    if ($normEan && IdentityNormalizer::isLikelyValidEan($normEan)) {
      $found = $this->safeCall('findByEan', $normEan);
      if ($found) {
        $resolved = $this->finalizeResolve($found, 'ean', $attrs, $isVar, $notes);
        Log::debug('WooParentResolver: matched by EAN', $resolved);
        $resolved['normalized'] = $normalized;
        return $resolved;
      }
      $notes[] = "EAN nicht gefunden: {$normEan}";
      Log::debug('WooParentResolver: no match by EAN', ['ean' => $normEan]);
    }

    if ($normMpn) {
      $found = $this->safeCall('findByMpn', $normMpn);
      if ($found) {
        $resolved = $this->finalizeResolve($found, 'mpn', $attrs, $isVar, $notes);
        Log::debug('WooParentResolver: matched by MPN', $resolved);
        $resolved['normalized'] = $normalized;
        return $resolved;
      }
      $notes[] = "MPN nicht gefunden: {$normMpn}";
      Log::debug('WooParentResolver: no match by MPN', ['mpn' => $normMpn]);
    }

    if ($composite) {
      $found = $this->safeCall('findBySku', $composite);
      if ($found) {
        $resolved = $this->finalizeResolve($found, 'composite', $attrs, $isVar, $notes);
        Log::debug('WooParentResolver: matched by Composite-SKU', $resolved);
        $resolved['normalized'] = $normalized;
        return $resolved;
      }
      $notes[] = "Composite-SKU nicht gefunden: {$composite}";
      Log::debug('WooParentResolver: no match by Composite-SKU', ['composite' => $composite]);
    }

    // 3) Kein Treffer → Neuanlage
    return $this->notFound($normalized, $notes);
  }

  /**
   * Finalisiert das Resolve-Ergebnis und versucht – falls nötig – die Variation unterhalb des Parents zu ermitteln.
   *
   * @param  array  $found
   * @param  string $matchedBy   'sku'|'ean'|'mpn'|'composite'
   * @param  array  $attrs
   * @param  bool   $isVar
   * @param  array  $notes
   * @return array
   */
  protected function finalizeResolve(array $found, string $matchedBy, array $attrs, bool $isVar, array &$notes): array
  {
    $itemType = $this->safeCall('getItemType', $found) ?? 'simple';
    $itemId   = (int) $this->safeCall('getItemId', $found);
    $parentId = $this->safeCall('getParentId', $found);

    $notes[] = "Match via {$matchedBy} ({$itemType}, id={$itemId}, parent=" . ($parentId ?? 'null') . ")";

    if ($isVar) {
      if ($itemType === 'variation') {
        return [
          'found'        => true,
          'type'         => 'variation',
          'product_id'   => $parentId ?: null,
          'variation_id' => $itemId,
          'matched_by'   => $matchedBy,
          'normalized'   => [],
          'notes'        => $notes,
        ];
      }

      $pid = $itemType === 'parent' ? $itemId : ($parentId ?: $itemId);
      $vid = $this->safeCall('findVariantUnderParentByAttributes', $pid, $attrs);

      if ($vid) {
        $notes[] = "Variation unter Parent gefunden (parent={$pid}, variation={$vid})";
        return [
          'found'        => true,
          'type'         => 'variation',
          'product_id'   => $pid,
          'variation_id' => (int) $vid,
          'matched_by'   => $matchedBy,
          'normalized'   => [],
          'notes'        => $notes,
        ];
      }

      $notes[] = "Keine passende Variation unter Parent gefunden (parent={$pid}) – ggf. Neuanlage der Variation.";
      return [
        'found'        => true,
        'type'         => 'parent',
        'product_id'   => $pid,
        'variation_id' => null,
        'matched_by'   => $matchedBy,
        'normalized'   => [],
        'notes'        => $notes,
      ];
    }

    return [
      'found'        => true,
      'type'         => $itemType === 'variation' ? 'variation' : ($itemType === 'parent' ? 'parent' : 'simple'),
      'product_id'   => $itemType === 'variation' ? ($parentId ?: null) : $itemId,
      'variation_id' => $itemType === 'variation' ? $itemId : null,
      'matched_by'   => $matchedBy,
      'normalized'   => [],
      'notes'        => $notes,
    ];
  }

  /**
   * Standardisierte "nicht gefunden"-Antwort.
   */
  protected function notFound(array $normalized, array $notes): array
  {
    $notes[] = 'Kein bestehender Woo-Eintrag gefunden – Neuanlage erforderlich.';
    Log::debug('WooParentResolver: not found', ['normalized' => $normalized]);

    return [
      'found'        => false,
      'type'         => 'none',
      'product_id'   => null,
      'variation_id' => null,
      'matched_by'   => null,
      'normalized'   => $normalized,
      'notes'        => $notes,
    ];
  }

  /**
   * Sichere Repo-Aufrufe mit Log-Ausgabe bei fehlender Methode/Fehlern.
   *
   * @param  string $method
   * @param  mixed  ...$args
   * @return mixed|null
   */
  protected function safeCall(string $method, ...$args)
  {
    if (!method_exists($this->repo, $method)) {
      Log::warning('WooParentResolver: Repository-Methode fehlt', ['method' => $method]);
      return null;
    }

    try {
      return $this->repo->{$method}(...$args);
    } catch (\Throwable $e) {
      Log::warning('WooParentResolver: Repository-Aufruf fehlgeschlagen', [
        'method'  => $method,
        'message' => $e->getMessage(),
      ]);
      return null;
    }
  }
}
