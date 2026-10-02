<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kask Import Mapping
    |--------------------------------------------------------------------------
    */

    'reference' => 'PART #',

    /*
     * Gruppiert Basisartikel und Varianten über den Artikelnummer-Stamm.
     *
     * Beispiele:
     * WAC00001-     -> WAC00001
     * WAC00001-001  -> WAC00001
     * WAC00053-240  -> WAC00053
     */
    'group_by_transform' => function (array $row): ?string {
        $partNumber = trim((string) ($row['PART #'] ?? ''));

        if ($partNumber === '') {
            return null;
        }

        return preg_replace('/-(?:\d+)?$/', '', $partNumber);
    },

    /*
     * Nur Artikelnummern mit numerischem Suffix sind echte Varianten.
     *
     * WAC00001-      -> Parent / Basiszeile
     * WAC00001-001   -> Variante
     */
    'variation_row_filter' => function (array $row): bool {
        $partNumber = trim((string) ($row['PART #'] ?? ''));

        return preg_match('/-\d+$/', $partNumber) === 1;
    },

    /*
     * Felder des Hauptprodukts.
     */
    'product' => [
        'product_number'        => 'PART #',
        'product_name'          => 'DESCRIPTION',
        'original_product_name' => 'DESCRIPTION',
        'description'           => 'DESCRIPTION',
        'ean'                   => 'EAN CODE',

        'weight'                => 'NET WEIGHT',

        'dimension_height_mm' => 'SHEIGHT',
        'dimension_width_mm'  => 'SWIDHT',
        'dimension_length_mm' => 'SLENGHT',

        'box_height' => 'MHEIGHT',
        'box_width'  => 'MWIDHT',
        'box_length' => 'MLENGHT',

        'hs_code'               => 'TARIF CODE',
        'country_of_origin'     => 'COUNTRY OF ORIGIN',
    ],

    /*
     * Felder einer Variante.
     */
    'variation_fields' => [
        'sku' => 'PART #',
        'ean' => 'EAN CODE',

        'weight' => [
            'columns' => 'NET WEIGHT',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return (int) round(
                    ((float) str_replace(',', '.', (string) $value)) * 1000
                );
            },
        ],

        'height_mm' => [
            'columns' => 'SHEIGHT',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return (int) round(
                    ((float) str_replace(',', '.', (string) $value)) * 10
                );
            },
        ],

        'width_mm' => [
            'columns' => 'SWIDHT',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return (int) round(
                    ((float) str_replace(',', '.', (string) $value)) * 10
                );
            },
        ],

        'length_mm' => [
            'columns' => 'SLENGHT',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                return (int) round(
                    ((float) str_replace(',', '.', (string) $value)) * 10
                );
            },
        ],
    ],

    'variation' => [
        'Farbe'    => 'KASK_COLOR',
        'Farbcode' => 'KASK_COLOR_CODE',
        'Größe'    => 'KASK_SIZE',
    ],

    /*
     * Produktgewicht: kg -> g.
     */
    'transforms' => [
        'weight' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 1000
            );
        },

        'dimension_height_mm' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },

        'dimension_width_mm' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },

        'dimension_length_mm' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },

        'box_height' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },

        'box_width' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },

        'box_length' => function ($value) {
            if ($value === null || trim((string) $value) === '') {
                return null;
            }

            return (int) round(
                ((float) str_replace(',', '.', (string) $value)) * 10
            );
        },
    ],
];