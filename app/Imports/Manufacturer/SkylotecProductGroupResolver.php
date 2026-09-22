<?php

namespace App\Imports\Manufacturer;

class SkylotecProductGroupResolver
{
    public static function resolve(array $row): ?string
    {
        $sku = trim((string) ($row['Original Skylotec Artikelnummer'] ?? ''));

        if ($sku === '') {
            return null;
        }

        /*
         * 1. Dimension direkt am SKU-Ende
         *
         * G-1132-WS-XS/M  + Dimension XS/M
         * -> G-1132-WS
         */
        $dimension = trim((string) ($row['Dimension'] ?? ''));

        if ($dimension !== '' && str_ends_with($sku, '-'.$dimension)) {
            return substr($sku, 0, -(strlen($dimension) + 1));
        }

        /*
         * 1a. Dimension als reine Meterangabe.
         *
         * L-0769-10  + Dimension 10m
         * -> L-0769
         *
         * L-0769-2,5 + Dimension 2,5m
         * -> L-0769
         *
         * Echte Abmessungen wie 550x25x4,5mm werden hier bewusst
         * nicht berücksichtigt.
         */
        if (
            $dimension !== ''
            && preg_match('/^(\d+(?:[.,]\d+)?)\s*m$/u', $dimension, $matches)
        ) {
            $groupKey = self::resolveNumericSuffix($sku, $matches[1]);

            if ($groupKey !== null) {
                return $groupKey;
            }
        }

        /*
         * 2. Größe direkt am SKU-Ende.
         *
         * Neben der Schreibweise aus der CSV wird auch die bei Skylotec
         * vorkommende SKU-Schreibweise mit "/" berücksichtigt.
         *
         * G-2000-STD-L/XL + Größe L-XL
         * -> G-2000-STD
         */
        $size = trim((string) ($row['Größe'] ?? ''));

        if ($size !== '') {
            $candidates = array_unique([
                $size,
                str_replace('-', '/', $size),
            ]);

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && str_ends_with($sku, '-'.$candidate)) {
                    return substr($sku, 0, -(strlen($candidate) + 1));
                }
            }
        }

        /*
         * 3. Längenangaben am SKU-Ende.
         *
         * L-0520-100 + Seillänge 100.00
         * -> L-0520
         *
         * L-1009-15 + Länge Verbindungsmittel 15.00
         * -> L-1009
         */
        $lengthFields = [
            'Seillänge',
            'Länge Verbindungsmittel',
        ];

        foreach ($lengthFields as $field) {
            $length = trim((string) ($row[$field] ?? ''));

            if ($length !== '') {
                $groupKey = self::resolveNumericSuffix($sku, $length);

                if ($groupKey !== null) {
                    return $groupKey;
                }
            }
        }

        /*
         * 4. Bekleidungsgröße direkt am SKU-Ende.
         *
         * BE-1002-900-XL + Kleidergröße XL
         * -> BE-1002-900
         */
        $clothingSize = trim((string) ($row['Kleidergröße'] ?? ''));

        if (
            $clothingSize !== ''
            && str_ends_with($sku, '-'.$clothingSize)
        ) {
            return substr($sku, 0, -(strlen($clothingSize) + 1));
        }

        /*
         * Bekleidungsgrößen wie W34/L34 verwenden teilweise nur einen
         * numerischen Varianten-Code am SKU-Ende.
         */
        if (
            $clothingSize !== ''
            && preg_match('/^(.+)-\d+$/', $sku, $matches)
        ) {
            return $matches[1];
        }

        /*
         * 5. Farbcode vor konstantem OS-Suffix.
         *
         * HP-6300-200-OS
         * HP-6300-001-OS
         * -> HP-6300-OS
         */
        $color = trim((string) ($row['Farbe'] ?? ''));

        if (
            $color !== ''
            && preg_match('/^(.+)-\d{3}-OS$/', $sku, $matches)
        ) {
            return $matches[1].'-OS';
        }

        /*
         * Kein sicher erkanntes Variantenmuster:
         * Artikel bleibt eine eigene Gruppe.
         */
        return $sku;
    }

    /**
     * Entfernt einen numerischen Variantenwert am Ende einer Artikelnummer.
     */
    private static function resolveNumericSuffix(
        string $sku,
        string $value
    ): ?string {
        $normalized = str_replace(',', '.', trim($value));

        if (str_contains($normalized, '.')) {
            $normalized = rtrim(rtrim($normalized, '0'), '.');
        }

        $candidates = array_unique([
            $normalized,
            str_replace('.', ',', $normalized),
        ]);

        foreach ($candidates as $candidate) {
            if (
                $candidate !== ''
                && str_ends_with($sku, '-'.$candidate)
            ) {
                return substr($sku, 0, -(strlen($candidate) + 1));
            }
        }

        return null;
    }
}
