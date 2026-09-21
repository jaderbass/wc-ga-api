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
         * 2. Größe direkt am SKU-Ende
         *
         * SET-559-000-XS-S + Größe XS-S
         * -> SET-559-000
         */
        $size = trim((string) ($row['Größe'] ?? ''));

        if ($size !== '' && str_ends_with($sku, '-'.$size)) {
            return substr($sku, 0, -(strlen($size) + 1));
        }

        /*
         * 3. Seillänge am SKU-Ende
         *
         * L-0520-100 + 100.00
         * -> L-0520
         *
         * L-0084-2,5 + 2.50
         * -> L-0084
         */
        $ropeLength = trim((string) ($row['Seillänge'] ?? ''));

        if ($ropeLength !== '') {
            $normalized = rtrim(rtrim(str_replace(',', '.', $ropeLength), '0'), '.');

            $candidates = array_unique([
                $normalized,
                str_replace('.', ',', $normalized),
            ]);

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && str_ends_with($sku, '-'.$candidate)) {
                    return substr($sku, 0, -(strlen($candidate) + 1));
                }
            }
        }

        /*
         * 4. Bekleidungsgröße.
         *
         * BE-479-000-34 + Kleidergröße W34/L34
         * -> BE-479-000
         *
         * Der letzte numerische SKU-Teil ist hier der Varianten-Code.
         */
        $clothingSize = trim((string) ($row['Kleidergröße'] ?? ''));

        if ($clothingSize !== '' && preg_match('/^(.+)-\d+$/', $sku, $matches)) {
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
}
