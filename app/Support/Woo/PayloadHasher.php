<?php

namespace App\Support\Woo;

/**
 * Hilfsklasse zur Bildung eines stabilen Hashes für Woo-Payloads.
 *
 * Zweck:
 * - Schnell erkennen, ob sich die an Woo gesendete Nutzlast (Payload) seit dem letzten Sync geändert hat.
 * - Der Hash muss deterministisch sein (unabhängig von der Schlüsselreihenfolge in Arrays).
 *
 * Verwendung:
 *   $hash = PayloadHasher::make($payload);
 *   // Hash z. B. in products.payload_hash speichern und mit onlyChanged vergleichen
 */
final class PayloadHasher
{
    /**
     * Erzeugt einen stabilen, deterministischen Hash (z. B. SHA-256) für die übergebene Payload.
     *
     * Anforderungen:
     * - Reihenfolge der Schlüssel darf das Ergebnis nicht beeinflussen (kanonische Serialisierung).
     * - Ergebnis ist ein hexadezimaler String.
     *
     * @param array<string,mixed> $payload  Die zu hashende Woo-Payload (assoziativ/verschachtelt)
     * @return string                        Hex-kodierter Hashwert (z. B. "a3c1…")
     */
    public static function make(array $payload): string
    {
        $normalized = json_encode(self::ksortRecursive($payload), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        return hash('sha256', $normalized ?: '');
    }

    private static function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = self::ksortRecursive($v);
            }
        }
        return $arr;
    }
}
