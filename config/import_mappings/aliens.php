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

        // Präfixe
        'product_sku_prefix'    => 'ALIENS-P-',
        'variation_sku_prefix'  => 'ALIENS-V-',
    ],

    /**
     * Produkt-Mapping (CSV → products.*)
     * Hinweis: Nur Felder die als Spalten existieren und sinnvoll sind.
     */
    'product' => [
        'product_name'      => ['Produktname'],
        'product_number'    => ['Referenz'],
        'ean'               => ['EAN-13'],
        'description'       => ['Beschreibung'],
        'short_description' => ['Kurzbeschreibung'],
        'external_url'      => ['Produkt-URL'],

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
    ],

];
