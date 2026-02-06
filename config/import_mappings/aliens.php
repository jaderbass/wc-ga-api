<?php

/**
 * Hersteller-Mapping: Aliens (PrestaShop-Export)
 *
 * Dieses Mapping sorgt dafür, dass die Alien-Cams nicht nur in Felsrissen
 * funktionieren, sondern auch sauber in unserem Datenmodell landen. Die
 * berühmten Farben – wir bringen Ordnung rein. Versprochen.
 *
 * Ziel:
 * - Parent SKU:  ALIENS-P-<Produkt-ID>
 * - Var SKU:     ALIENS-V-<Kombination-ID>
 * - Attribute Group:* wird automatisch als Varianten-Attribute importiert (Pivot)
 * - Feature:* wird automatisch in product_meta gespeichert
 *
 * Pfad: config/import_mappings/aliens.php
 *
 * Verantwortlichkeiten:
 * - Abgleich der Alien-Farbcodes mit internen Werten
 * - Zuordnung der technischen Daten (Größe, Range, kN)
 * - Vereinheitlichung sehr kreativer Farbnamen
 *
 * Hinweis:
 * Dieses Mapping ist bewusst schlank gehalten, weil Aliens sehr viele Feature-/Attribute-Group-Spalten liefert.
 *
 * Fun Fact:
 * Diese Cams heißen nicht ohne Grund „Aliens“. Manchmal wirken auch die Daten so. 😉
 *
 * @mapping-source   Aliens CSV
 * @mapping-target   InternalProductDTO
 * @see App\Imports\ImporterForAliens
 */

use Illuminate\Support\Str;

return [

    // Identifikation & Gruppierung
    'group_by'  => ['Produkt-ID'],
    'reference' => ['Kombination-ID'],

    /**
     * Steuerflags für den GenericCsvProductImporter (neue Erweiterungen)
     */
    'flags' => [
        // Produkt soll über SKU gefunden werden (nicht über slug), weil wir Prefix-SKUs nutzen
        'product_lookup_by' => 'sku',

        // Zusätzliche automatische Verarbeitung:
        'auto_attribute_groups' => true,
        'auto_features'         => true,
        'debug_features'        => true,

        // Präfixe
        'product_sku_prefix'    => 'ALIENS-P-',
        'variation_sku_prefix'  => 'ALIENS-V-',
    ],

    'feature_meta_whitelist' => [
        'Feature: Normen' => 'norms',
        'Feature: Typ' => 'type',
        'Feature: Material' => 'materials',
        'Feature: Farbe' => 'color',

        // Bruchlast/Festigkeit (Aliens hat viele Varianten, nimm die wichtigsten)
        'Feature: Mindestbruchlast [kN]' => 'min_break_load_kn',
        'Feature: Mindestbruchlast geschlossen [kN]' => 'break_load_closed_kn',
        'Feature: Mindestbruchlast offen [kN]' => 'break_load_open_kn',
        'Feature: Mindestbruchlast quer [kN]' => 'break_load_cross_kn',
        'Feature: Mindestbruchlast längs [kN]' => 'break_load_long_kn',
        'Feature: Festigkeit / Bruchlast / Belastbarkeit [kN]' => 'break_load_kn',

        // Seil / Normstürze / Fangstoß (Beispiele)
        'Feature: Anzahl Normstürze [UIAA]' => 'uiaa_falls',
        'Feature: Max. Fangstoß [kN]' => 'max_impact_force_kn',
        'Feature: Statische Dehnung [%]' => 'static_elongation_pct',
        'Feature: Dynamische Dehnung [%]' => 'dynamic_elongation_pct',
        'Feature: Mantelverschiebung [%]' => 'sheath_slippage_pct',

        // Maße / Durchmesser
        'Feature: Durchmesser [mm]' => 'diameter_mm',
        'Feature: Breite [mm]' => 'width_mm_feature',
    ],

    /**
     * Produkt-Mapping (CSV → products.*)
     * Hinweis: Nur Felder die als Spalten existieren und sinnvoll sind.
     */
    'product' => [
        'product_name'      => ['Produktname'],
        'product_number'    => ['Referenz', 'Kombinations-Referenz'],
        'ean'               => ['EAN-13'],
        'description'       => ['Beschreibung'],
        'short_description' => ['Kurzbeschreibung'],
        'external_url'      => ['Produkt-URL'],
        'type'              => ['Feature: Typ'],
        'materials'         => ['Feature: Material'],
        'norms'             => ['Feature: Normen'],
        // optional, falls vorhanden:
        // 'made_in'   => ['Hergestellt in'],

        // Maße/Gewicht (kommen bei Aliens oft 0/leer – Transform setzt nur bei >0)
        'width_mm'          => ['Breite'],
        'height_mm'         => ['Höhe'],
        'length_mm'         => ['Tiefe'],
        'weight_g'          => ['Gewicht'],

        // Slug (UNIQUE) – wenn leer, wird später aus Produktname gebaut
        'slug'              => ['Suchmaschinenfreundliche URL'],
    ],

    /**
     * Varianten-Mapping (CSV → product_variations.*)
     */
    'variation_fields' => [
        'ean'            => ['Kombination EAN13'],
        'stock_quantity' => ['Kombinationsmenge'],

        // Optional: wenn ihr Variation-Maße nutzen wollt (Aliens liefert oft nur Produktmaße)
        // 'weight_g'       => ['Gewicht'],
        // 'length_mm'      => ['Tiefe'],
        // 'width_mm'       => ['Breite'],
        // 'height_mm'      => ['Höhe'],
    ],

    /**
     * Optional: explizite Variant-Attribute (nicht nötig, weil auto_attribute_groups=true)
     * 'variation' => [...],
     */

    'transforms' => [

        /**
         * SKU-Strategie (Prefix)
         * - product.sku = ALIENS-P-<Produkt-ID>
         * - variation.sku = ALIENS-V-<Kombination-ID>
         *
         * Hinweis: product.sku wird im Importer gesetzt, nicht über Mapping,
         * weil 'sku' nicht im 'product' mapping steht (wir wollen slug + sku getrennt behandeln).
         */

        // Normalisiere mm/weight nur wenn >0
        'width_mm' => function ($v) {
            if ($v === null) return null;
            $s = is_string($v) ? trim($v) : (string)$v;
            if ($s === '') return null;

            $s = str_replace([' ', '.'], '', $s);
            $s = str_replace(',', '.', $s);

            if (is_numeric($s)) {
                $n = (int) round((float) $s);
                return $n > 0 ? $n : null;
            }

            if (preg_match('/\d+/', (string) $v, $m)) {
                $n = (int) $m[0];
                return $n > 0 ? $n : null;
            }

            return null;
        },

        'height_mm' => function ($v) {
            if ($v === null) return null;
            $s = is_string($v) ? trim($v) : (string)$v;
            if ($s === '') return null;

            $s = str_replace([' ', '.'], '', $s);
            $s = str_replace(',', '.', $s);

            if (is_numeric($s)) {
                $n = (int) round((float) $s);
                return $n > 0 ? $n : null;
            }

            if (preg_match('/\d+/', (string) $v, $m)) {
                $n = (int) $m[0];
                return $n > 0 ? $n : null;
            }

            return null;
        },

        'length_mm' => function ($v) {
            if ($v === null) return null;
            $s = is_string($v) ? trim($v) : (string)$v;
            if ($s === '') return null;

            $s = str_replace([' ', '.'], '', $s);
            $s = str_replace(',', '.', $s);

            if (is_numeric($s)) {
                $n = (int) round((float) $s);
                return $n > 0 ? $n : null;
            }

            if (preg_match('/\d+/', (string) $v, $m)) {
                $n = (int) $m[0];
                return $n > 0 ? $n : null;
            }

            return null;
        },

        'weight_g' => function ($v) {
            if ($v === null) return null;
            $s = is_string($v) ? trim($v) : (string)$v;
            if ($s === '') return null;

            $s = str_replace([' ', '.'], '', $s);
            $s = str_replace(',', '.', $s);

            if (is_numeric($s)) {
                $n = (int) round((float) $s);
                return $n > 0 ? $n : null;
            }

            if (preg_match('/\d+/', (string) $v, $m)) {
                $n = (int) $m[0];
                return $n > 0 ? $n : null;
            }

            return null;
        },

        // EAN nur Ziffern
        'ean' => function ($v) {
            if (!is_string($v)) return $v;
            $digits = preg_replace('/\D+/', '', $v) ?? '';
            return $digits !== '' ? $digits : null;
        },

        // Slug fallback
        'slug' => function ($v, array $row = []) {
            $raw = is_string($v) ? trim($v) : '';
            if ($raw !== '') {
                return \Illuminate\Support\Str::slug($raw);
            }
            $name = (string)($row['Produktname'] ?? '');
            $name = trim($name);
            return $name !== '' ? \Illuminate\Support\Str::slug($name) : null;
        },

        'product_number' => function ($v, array $row = []) {
            $ref  = trim((string)($row['Referenz'] ?? ''));
            $comb = trim((string)($row['Kombinations-Referenz'] ?? ''));

            if ($ref !== '') {
                logger()->debug('[Aliens Import] product_number from Referenz', [
                    'value'       => $ref,
                    'product_id'  => $row['Produkt-ID'] ?? null,
                    'variant_id'  => $row['Kombination-ID'] ?? null,
                ]);

                return $ref;
            }

            if ($comb !== '') {
                logger()->debug('[Aliens Import] product_number from Kombinations-Referenz', [
                    'value'       => $comb,
                    'product_id'  => $row['Produkt-ID'] ?? null,
                    'variant_id'  => $row['Kombination-ID'] ?? null,
                ]);

                return $comb;
            }

            logger()->warning('[Aliens Import] product_number missing', [
                'product_id' => $row['Produkt-ID'] ?? null,
                'row'        => $row,
            ]);

            return null;
        },


        '_build_feature_meta' => function ($v, array $row = [], array $mapping = []) {
            // $mapping ist je nach Importer evtl. nicht verfügbar – falls nicht, lass es weg
            $whitelist = $mapping['feature_meta_whitelist'] ?? [];

            $meta = [];

            foreach ($whitelist as $csvHeader => $metaKey) {
                $val = $row[$csvHeader] ?? null;

                if ($val === null) {
                    continue;
                }

                $val = is_string($val) ? trim($val) : $val;
                if ($val === '' || $val === '0') {
                    // bei manchen Features ist 0 sinnvoll – wenn du 0 behalten willst, entferne '|| $val === "0"'
                    continue;
                }

                $meta[$metaKey] = $val;
            }

            return $meta;
        },

        // Punkte-Trenner in Kommas umwandeln
        // ! nur wenn benötigt!
        // 'norms' => function ($v) {
        //     if ($v === null) return null;
        //     $s = trim((string) $v);
        //     if ($s === '') return null;

        //     $s = str_replace(['•', '·', '|'], ',', $s);
        //     $s = preg_replace('/\s*,\s*/', ', ', $s);
        //     $s = preg_replace('/\s+/', ' ', $s);

        //     return trim($s, " ,");
        // },
    ],

];
