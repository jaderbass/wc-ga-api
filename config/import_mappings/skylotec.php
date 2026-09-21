<?php

use App\Imports\Manufacturer\SkylotecProductGroupResolver;

return [
    'reference' => 'Original Skylotec Artikelnummer',

    'group_by_transform' => function (array $row): ?string {
        return SkylotecProductGroupResolver::resolve($row);
    },

    'product' => [
        'product_number' => 'Original Skylotec Artikelnummer',
        'sku' => 'Original Skylotec Artikelnummer',
        'product_name' => 'Produktname',
        'original_product_name' => 'Produktname',
        'ean' => 'GTIN',

        'short_description' => 'Kurzbeschreibung Professional',
        'description' => 'Produktbeschreibung (HTML-Format)',

        'manufacturer_price_cents' => 'Nettopreis EU Aktuell',
        'weight_g' => 'Nettogewicht',

        'image_urls' => 'Link Standardbild',
        'online_sellable' => 'Online verkaufbar',
    ],

    'transforms' => [
        'manufacturer_price_cents' => function ($v) {
            if ($v === null) {
                return null;
            }

            $s = is_string($v) ? trim($v) : (string) $v;

            if ($s === '') {
                return null;
            }

            $s = str_replace(["\u{00A0}", ' ', '€'], '', $s);
            $s = str_replace(',', '.', $s);

            if (! is_numeric($s)) {
                return null;
            }

            $cents = (int) round(((float) $s) * 100);

            return $cents >= 0 ? $cents : null;
        },

        'weight_g' => function ($v) {
            if ($v === null) {
                return null;
            }

            $s = is_string($v) ? trim($v) : (string) $v;

            if ($s === '') {
                return null;
            }

            $s = str_replace(',', '.', $s);

            if (! is_numeric($s)) {
                return null;
            }

            return (int) round(((float) $s) * 1000);
        },

        'image_urls' => function ($v) {
            if ($v === null) {
                return null;
            }

            $url = trim((string) $v);

            return $url !== '' ? [$url] : null;
        },

        'online_sellable' => function ($v) {

            if ($v === null || trim((string) $v) === '') {
                return null;
            }

            $value = filter_var(
                $v,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($value === null) {
                return null;
            }

            return $value ? 1 : 0;
        },
    ],

    'variation_fields' => [
        'sku' => 'Original Skylotec Artikelnummer',
        'ean' => 'GTIN',

        'manufacturer_price_cents' => [
            'columns' => 'Nettopreis EU Aktuell',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                $s = str_replace(["\u{00A0}", ' ', '€'], '', trim((string) $value));
                $s = str_replace(',', '.', $s);

                if (! is_numeric($s)) {
                    return null;
                }

                return (int) round(((float) $s) * 100);
            },
        ],

        'weight_g' => [
            'columns' => 'Nettogewicht',
            'transform' => function ($value) {
                if ($value === null || trim((string) $value) === '') {
                    return null;
                }

                $s = str_replace(',', '.', trim((string) $value));

                if (! is_numeric($s)) {
                    return null;
                }

                return (int) round(((float) $s) * 1000);
            },
        ],
    ],

    'variation' => [
        'Dimension' => 'Dimension',
        'Größe' => 'Größe',
        'Kleidergröße' => 'Kleidergröße',
        'Seillänge' => 'Seillänge',
        'Farbe' => 'Farbe',
    ],
];
