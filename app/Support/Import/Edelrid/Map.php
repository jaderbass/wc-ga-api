<?php

namespace App\Support\Import\Edelrid;

use App\Support\Import\ImportValueNormalizer as V;

class Map
{
    /**
     * Trimmt Produktnamen, falls String.
     *
     * @param mixed $v
     * @return mixed
     */
    public static function productName($v)
    {
        return is_string($v) ? trim($v) : $v;
    }

    /**
     * Trimmt Beschreibung, falls String.
     *
     * @param mixed $v
     * @return mixed
     */
    public static function description($v)
    {
        return is_string($v) ? trim($v) : $v;
    }

    /**
     * Extrahiert nur Ziffern; leere Ergebnisse -> null.
     */
    public static function ean($v): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $v);
        return $digits !== '' ? $digits : null;
    }

    /**
     * Normalisiert Gewicht auf Gramm (int) oder null.
     */
    public static function weightGrams($v): ?int
    {
        $g = V::toGrams($v);
        return $g !== null ? (int) $g : null;
        // Falls V::toGrams bereits int|null liefert, ist das Cast unkritisch.
    }

    /**
     * Parst Roh-Maße (z. B. "130 x 76" oder "130x76x45") in Millimeter-Werte.
     * Gibt ein Array [L, B, H] (1–3 Werte) oder null zurück.
     */
    public static function dimensionsRaw($v): ?array
    {
        if (!$v) {
            return null;
        }

        // Erlaube nur Ziffern, x/X, Komma, Punkt, Leerzeichen
        $s = preg_replace('/[^0-9xX,.\s]/', '', (string) $v);
        if ($s === null || $s === '') {
            return null;
        }

        // Split an 'x' bzw. 'X'
        $parts = preg_split('/[xX]/', $s);
        if (!$parts) {
            return null;
        }

        // In mm umrechnen (z. B. "13.0" cm -> 130 mm) via Normalizer
        $parts = array_map(function ($p) {
            $p = trim((string) $p);
            return V::toMillimeters($p);
        }, $parts);

        // Nulls entfernen, Indizes glätten
        $parts = array_values(array_filter($parts, static function ($n) {
            return $n !== null;
        }));

        return $parts ?: null;
    }

    /**
     * Normalisiert Bild-URL-Listen; akzeptiert Trennzeichen ',', ';', '|'.
     *
     * @return array<int,string>
     */
    public static function imageUrls($v): array
    {
        return V::normalizeUrlList($v, [',', ';', '|']);
    }

    /**
     * Normalisiert Video-URL-Listen; akzeptiert Trennzeichen ',', ';', Zeilenumbruch.
     *
     * @return array<int,string>
     */
    public static function videoUrls($v): array
    {
        return V::normalizeUrlList($v, [',', ';', "\n"]);
    }
}
