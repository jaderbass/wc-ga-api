<?php

namespace App\Support;

/**
 * Class IdentityNormalizer
 *
 * Zweck:
 * - Einheitliche Normalisierung von SKU, MPN und EAN für stabile Vergleiche.
 * - Validierung einfacher EAN-Formate.
 * - Erzeugung deterministischer Composite-SKUs (z. B. aus Hersteller/MPN/Varianten).
 *
 * Wichtig:
 * - Laut Kunden-Setup ist die SKU in 99,98% der Fälle eindeutig → sie gilt als primärer Match-Key.
 * - EAN/MPN/Composite-SKU werden nur als Fallback genutzt, falls keine SKU vorhanden ist.
 *
 * Hinweise:
 * - Diese Klasse enthält keine I/O- oder API-Logik und ist frei von Framework-Abhängigkeiten.
 * - Anpassungen an den Regeln (z. B. erlaubte Zeichen) sind zentral möglich.
 *
 * @package App\Support
 */

class IdentityNormalizer
{
  /**
   * Normalisiert eine SKU für verlässliche Vergleiche.
   *
   * Regeln:
   * - trim
   * - Unicode-Normalisierung (falls nötig hier einfach mb_convert_case)
   * - Nur A–Z, 0–9, Bindestrich, Unterstrich, Punkt; Leerzeichen → Bindestrich
   * - Mehrfache Trenner reduzieren
   *
   * @param  string|null $sku
   * @return string|null  Null, wenn Eingang leer/Null
   */
  public static function normalizeSku(?string $sku): ?string
  {
    if ($sku === null) {
      return null;
    }

    $sku = trim($sku);
    if ($sku === '') {
      return null;
    }

    // Grossschreibung vereinheitlichen (SKU meist case-insensitive)
    $sku = mb_strtoupper($sku, 'UTF-8');

    // Leerzeichen → Bindestrich
    $sku = preg_replace('/\s+/u', '-', $sku);

    // Erlaubte Zeichen beibehalten, andere entfernen
    $sku = preg_replace('/[^A-Z0-9\-\._]/u', '', $sku);

    // Mehrere Bindestriche/Punkte/Unterstriche auf einen reduzieren
    $sku = preg_replace('/[\-]{2,}/', '-', $sku);
    $sku = preg_replace('/[_]{2,}/', '_', $sku);
    $sku = preg_replace('/[\.]{2,}/', '.', $sku);

    // Trennzeichen am Rand entfernen
    $sku = trim($sku, '-_.');

    return $sku !== '' ? $sku : null;
  }

  /**
   * Normalisiert eine MPN (Hersteller-Artikelnummer) für Vergleiche.
   *
   * Regeln analog SKU, jedoch etwas toleranter gegenüber Punkten/Slash.
   *
   * @param  string|null $mpn
   * @return string|null
   */
  public static function normalizeMpn(?string $mpn): ?string
  {
    if ($mpn === null) {
      return null;
    }

    $mpn = trim($mpn);
    if ($mpn === '') {
      return null;
    }

    $mpn = mb_strtoupper($mpn, 'UTF-8');
    $mpn = preg_replace('/\s+/u', '-', $mpn);
    $mpn = preg_replace('/[^A-Z0-9\-\._]/u', '', $mpn);

    $mpn = preg_replace('/[\-]{2,}/', '-', $mpn);
    $mpn = preg_replace('/[_]{2,}/', '_', $mpn);
    $mpn = preg_replace('/[\.]{2,}/', '.', $mpn);

    $mpn = trim($mpn, '-_.');

    return $mpn !== '' ? $mpn : null;
  }

  /**
   * Normalisiert eine EAN (nur Ziffern), behält führende Nullen bei.
   *
   * @param  string|null $ean
   * @return string|null
   */
  public static function normalizeEan(?string $ean): ?string
  {
    if ($ean === null) {
      return null;
    }

    $ean = trim($ean);
    if ($ean === '') {
      return null;
    }

    // Nur Ziffern zulassen
    $ean = preg_replace('/\D+/', '', $ean);

    return $ean !== '' ? $ean : null;
  }

  /**
   * Einfache EAN-Prüfung: Länge 8/12/13/14 und nur Ziffern.
   * (Optional könnte hier eine Checkdigit-Prüfung ergänzt werden.)
   *
   * @param  string|null $ean
   * @return bool
   */
  public static function isLikelyValidEan(?string $ean): bool
  {
    $ean = self::normalizeEan($ean);
    if ($ean === null) {
      return false;
    }

    $len = strlen($ean);
    return in_array($len, [8, 12, 13, 14], true);
  }

  /**
   * Erzeugt eine deterministische Composite-SKU aus Hersteller/MPN/Varianten.
   *
   * Beispiel:
   *   brand=Edelrid, mpn=72049 → EDELRID-72049
   *   brand=Petzl, mpn=A010EA00, attrs=['color'=>'red','size'=>'L'] → PETZL-A010EA00-RED-L
   *
   * @param  string|null $brand  Hersteller-/Markenname
   * @param  string|null $mpn    Hersteller-Artikelnummer
   * @param  array<string,string|null> $variantAttributes  geordnete (!) Attribute, z. B. ['color'=>'RED','size'=>'L']
   * @return string|null
   */
  public static function buildCompositeSku(?string $brand, ?string $mpn, array $variantAttributes = []): ?string
  {
    $brand = self::normalizeSku($brand);
    $mpn   = self::normalizeMpn($mpn);

    if (!$brand && !$mpn) {
      return null;
    }

    $parts = [];
    if ($brand) {
      $parts[] = $brand;
    }
    if ($mpn) {
      $parts[] = $mpn;
    }

    // Attribute in stabiler Reihenfolge anhängen (so wie übergeben)
    foreach ($variantAttributes as $value) {
      if ($value === null) {
        continue;
      }
      $norm = self::normalizeSku((string)$value);
      if ($norm) {
        $parts[] = $norm;
      }
    }

    $sku = implode('-', $parts);

    // Final nochmals harmonisieren
    $sku = preg_replace('/[\-]{2,}/', '-', $sku);
    $sku = trim($sku, '-');

    return $sku !== '' ? $sku : null;
  }
}
