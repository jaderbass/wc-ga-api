<?php

namespace App\Support\Import\Edelrid;

use App\Support\ImportValueNormalizer as V;
use Illuminate\Support\Facades\Log;

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
     * Parst Roh-Maße (z. B. "130 x 76" oder "13x7.6x4.5") in Millimeter-Werte.
     * Liefert ein Array mit den DB-Feldern:
     * - dimensions_raw
     * - dimension_length_mm
     * - dimension_width_mm
     * - dimension_height_mm
     *
     * Beibehaltung der bisherigen Logik + Erweiterung um strukturierte Felder.
     */
    public static function dimensionsRaw($v): ?array
    {
        if (!$v) {
            return [
                'dimensions_raw'       => null,
                'dimension_length_mm'  => null,
                'dimension_width_mm'   => null,
                'dimension_height_mm'  => null,
            ];
        }

        // Original unbereinigt speichern
        $raw = (string) $v;

        // Erlaube nur Ziffern, x/X, Komma, Punkt, Leerzeichen
        $s = preg_replace('/[^0-9xX,.\s]/', '', $raw);
        if ($s === null || $s === '') {
            return [
                'dimensions_raw'       => $raw,
                'dimension_length_mm'  => null,
                'dimension_width_mm'   => null,
                'dimension_height_mm'  => null,
            ];
        }

        // Split an 'x' bzw. 'X'
        $parts = preg_split('/[xX]/', $s);
        if (!$parts) {
            return [
                'dimensions_raw'       => $raw,
                'dimension_length_mm'  => null,
                'dimension_width_mm'   => null,
                'dimension_height_mm'  => null,
            ];
        }

        // In mm umrechnen (z. B. "13.0" cm -> 130 mm) via Normalizer
        $parts = array_map(function ($p) {
            $p = trim((string) $p);
            return V::toMillimeters($p);
        }, $parts);

        // Nulls entfernen, Indizes glätten
        $parts = array_values(array_filter($parts, static fn($n) => $n !== null));

        // Jetzt sauber zuweisen
        $length = $parts[0] ?? null;
        $width  = $parts[1] ?? null;
        $height = $parts[2] ?? null;

        return [
            'dimensions_raw'       => $raw,
            'dimension_length_mm'  => $length,
            'dimension_width_mm'   => $width,
            'dimension_height_mm'  => $height,
        ];
    }


    /**
     * Normalisiert Bild-URL-Listen; akzeptiert Trennzeichen ',', ';', '|'.
     *
     * @return array<int,string>
     */
    public static function imageUrls($v): array
    {
        // Wenn leer → kein Bild
        if ($v === null || $v === '') {
            return [];
        }

        $rawItems = [];

        // Wenn mehrere Spalten übergeben wurden
        if (is_array($v)) {
            foreach ($v as $item) {
                if ($item !== null && $item !== '') {
                    $rawItems[] = $item;
                }
            }
        } else {
            $rawItems[] = $v;
        }

        // Jetzt splitten wir manuell auf , ; |
        $all = [];
        foreach ($rawItems as $item) {
            $parts = preg_split('/[;,|]/', $item) ?: [$item];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $all[] = $p;
                }
            }
        }

        // Nur Strings behalten, ohne http/https-Filter
        $all = array_filter($all, fn($x) => is_string($x) && $x !== '');

        // Dubletten raus
        $all = array_values(array_unique($all));

        return $all;
    }



    /**
     * Normalisiert Video-URL-Listen.
     * Struktur identisch zu imageUrls(), aber für einzelne Video-Spalte.
     *
     * @return array<int,string>
     */
    public static function videoUrls($v): array
    {
        // Fall 1: nichts vorhanden → leeres Array
        if ($v === null || $v === '') {
            return [];
        }

        $all = [];

        // Fall 2: mehrere Video-Spalten als Array
        if (is_array($v)) {
            foreach ($v as $item) {
                if ($item === null || $item === '') {
                    continue;
                }

                // normalizeUrlList kann null zurückgeben → defensiv abfangen
                $urls = V::normalizeUrlList($item, [',', ';', '|']) ?? [];

                // kann nicht-array sein → in Array wandeln
                if (!is_array($urls)) {
                    $urls = [$urls];
                }

                $all = array_merge($all, $urls);
            }
        } else {
            // Fall 3: einfacher String
            $urls = V::normalizeUrlList($v, [',', ';', '|']) ?? [];

            if (!is_array($urls)) {
                $urls = [$urls];
            }

            $all = $urls;
        }

        if (empty($all)) {
            return [];
        }

        // sicherstellen, dass wir ein Array haben
        if (!is_array($all)) {
            $all = [$all];
        }

        // trimmen
        $all = array_map('trim', $all);

        // gültige URLs filtern
        $all = array_filter($all, function ($url) {
            return is_string($url)
                && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
        });

        // Duplikate entfernen
        $all = array_values(array_unique($all));

        return $all;
    }
}
