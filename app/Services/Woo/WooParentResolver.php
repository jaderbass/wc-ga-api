<?php

namespace App\Services\Woo;

use App\Support\IdentityNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class WooParentResolver
 *
 * Zweck:
 * - Ermittelt für ein zu exportierendes Produkt (Parent oder Variante), ob in WooCommerce
 *   bereits ein passender Datensatz existiert – gemäß Kunden-Setup ist die SKU der primäre Match-Key.
 * - Nutzt Fallbacks (EAN → MPN → Composite-SKU) nur, wenn keine SKU vorhanden ist.
 * - Liefert eine strukturierte Resolve-Antwort (Parent-/Variation-IDs, matchedBy, normalisierte Keys).
 *
 * Wichtige Annahmen:
 * - Die SKU ist in 99,98% der Fälle eindeutig und hat Priorität.
 * - Bei vorhandener SKU wird ausschließlich nach SKU gematcht (keine parallelen Fallbacks).
 * - Fallbacks werden nur genutzt, wenn SKU fehlt.
 *
 * Abhängigkeiten:
 * - $repo: Ein Repository/Service, der Woo-spezifische Lookups bereitstellt.
 *   Erwartete Methoden (Bezeichnungen beispielhaft, bitte an dein Projekt anpassen):
 *     - findBySku(string $sku): array|null                      // Produkt oder Variante (mit Parent-Bezug)
 *     - findByEan(string $ean): array|null                      // Produkt oder Variante (mit Parent-Bezug)
 *     - findByMpn(string $mpn): array|null                      // Produkt oder Variante (mit Parent-Bezug)
 *     - findVariantUnderParentByAttributes(int $parentId, array $attributes): ?int // Variation-ID
 *     - getParentId(array $wooItem): ?int                       // Parent-ID für Variation oder null für Simple/Parent
 *     - getItemId(array $wooItem): int                          // ID des gefundenen Items (Parent oder Variation)
 *     - getItemType(array $wooItem): string                     // 'parent' | 'variation' | 'simple'
 *
 * Übergabe-Format $candidate (Beispiele – bitte an deinen Importer angleichen):
 * - [
 *     'sku'        => 'PETZL-A010EA00-RED-L', // optional
 *     'ean'        => '3342540833561',        // optional
 *     'mpn'        => 'A010EA00',             // optional
 *     'brand'      => 'Petzl',                // optional (für Composite-SKU)
 *     'attributes' => [                       // für Varianten-Auflösung unter einem Parent
 *         'color' => 'RED',
 *         'size'  => 'L'
 *     ],
 *     'is_variant' => true|false              // optional: Hinweis aus deinem Datenmodell
 *   ]
 *
 * Resolve-Rückgabe:
 * - [
 *     'found'        => bool,
 *     'type'         => 'parent'|'variation'|'simple'|'none',
 *     'product_id'   => int|null,             // Parent- oder Simple-ID (wenn vorhanden)
 *     'variation_id' => int|null,             // Variation-ID (wenn vorhanden)
 *     'matched_by'   => 'sku'|'ean'|'mpn'|'composite'|null,
 *     'normalized'   => [
 *        'sku' => ?string, 'ean' => ?string, 'mpn' => ?string, 'composite' => ?string
 *     ],
 *     'notes'        => string[]              // Diagnose/Debug-Hinweise
 *   ]
 *
 * Logging:
 * - Debug-Logs für jeden Matching-Schritt (Thema, normalisierter Wert, Ergebnis).
 * - Keine Backslashes vor Log:: (Kompatibilität mit Intelephense).
 *
 * @package App\Services\Woo
 */
class WooParentResolver
{
  /** @var object */
  protected $repo;

  /**
   * @param  object  $repo  Siehe erwartete Methoden in der Klassendoku.
   */
  public function __construct(object $repo)
  {
    $this->repo = $repo;
  }

  /**
   * Führt die Auflösung für ein Produkt/Variante durch.
   *
   * Matching-Strategie:
   * 1) Wenn SKU vorhanden: ausschließlich SKU-Match (kein Fallback!).
   * 2) Wenn keine SKU: EAN → MPN → Composite-SKU (falls generierbar).
   * 3) Bei Variantentyp: Versuche – falls Parent gefunden – passende Variation anhand Attribute zu bestimmen.
   *
   * @param  array $candidate  Siehe Klassendoku für das erwartete Format.
   * @return array             Resolve-Struktur (siehe Klassendoku).
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

    Log::debug('WooParentResolver: Start resolve', [
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
      // Bei vorhandener SKU KEIN Fallback → Neuanlage
      return $this->notFound($normalized, $notes);
    }

    // 2) Fallbacks nur wenn SKU fehlt
    // 2a) EAN
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

    // 2b) MPN
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

    // 2c) Composite-SKU (wenn generierbar)
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
   * @param  array       $found     Vom Repo geliefertes Woo-Item (Parent/Variation/Simple)
   * @param  string      $matchedBy 'sku'|'ean'|'mpn'|'composite'
   * @param  array       $attrs     Attributwerte für Variantenauflösung
   * @param  bool        $isVar     Ob Kandidat als Variante betrachtet wird
   * @param  array       $notes     Referenz auf Notizen (wird erweitert)
   * @return array
   */
  protected function finalizeResolve(array $found, string $matchedBy, array $attrs, bool $isVar, array &$notes): array
  {
    $itemType = $this->safeCall('getItemType', $found) ?? 'simple';
    $itemId   = (int) $this->safeCall('getItemId', $found);
    $parentId = $this->safeCall('getParentId', $found);

    $notes[] = "Match via {$matchedBy} ({$itemType}, id={$itemId}, parent=" . ($parentId ?? 'null') . ")";

    // Wenn es sich um eine Variante handelt und wir nur den Parent kennen, versuche die Variation via Attribute:
    if ($isVar) {
      // Falls der gefundene Datensatz bereits eine Variation ist, sind wir fertig
      if ($itemType === 'variation') {
        return [
          'found'        => true,
          'type'         => 'variation',
          'product_id'   => $parentId ?: null,
          'variation_id' => $itemId,
          'matched_by'   => $matchedBy,
          'normalized'   => [], // wird vom Aufrufer gesetzt
          'notes'        => $notes,
        ];
      }

      // Wenn Parent/Simple gefunden → versuche Variant unter Parent mit Attributen
      $pid = $itemType === 'parent' ? $itemId : ($parentId ?: $itemId);
      $vid = $this->safeCall('findVariantUnderParentByAttributes', $pid, $attrs);

      if ($vid) {
        $notes[] = "Variation unter Parent gefunden (parent={$pid}, variation={$vid})";
        return [
          'found'        => true,
          'type'         => 'variation',
          'product_id'   => $pid,
          'variation_id' => (int)$vid,
          'matched_by'   => $matchedBy,
          'normalized'   => [], // wird vom Aufrufer gesetzt
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
        'normalized'   => [], // wird vom Aufrufer gesetzt
        'notes'        => $notes,
      ];
    }

    // Kein Variantenszenario → Parent/Simple genügt
    return [
      'found'        => true,
      'type'         => $itemType === 'variation' ? 'variation' : ($itemType === 'parent' ? 'parent' : 'simple'),
      'product_id'   => $itemType === 'variation' ? ($parentId ?: null) : $itemId,
      'variation_id' => $itemType === 'variation' ? $itemId : null,
      'matched_by'   => $matchedBy,
      'normalized'   => [], // wird vom Aufrufer gesetzt
      'notes'        => $notes,
    ];
  }

  /**
   * Standardisierte "nicht gefunden"-Antwort.
   *
   * @param  array $normalized
   * @param  array $notes
   * @return array
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
