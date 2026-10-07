<?php

/**
 * Hersteller-Mapping: Singing Rock (XML/API)
 *
 * Pfad: config/import_mappings/singingrock.php
 *
 * Quelle (Tags):
 * - PRODUCTITEM/ARTICLE, ARTICLE_NAME, DESCRIPTION, SHORT_DESCRIPTION, EAN, WEIGHT, ...
 * - Medien: MAIN_PRODUCT_PICTURE, PICTURE_2..PICTURE_13, DOWNLOAD_VIDEO
 * - Varianten: VARIETY_COLOUR, VARIETY_SIZE
 * - Gruppen: GROUP_CODE, GROUP_CODE_NAME
 * - Kategorien: CATEGORIES/CATEGORY (Liste)
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Identifikation & Gruppierung
    |--------------------------------------------------------------------------
    */
    'reference' => 'ARTICLE',

    // Gruppierung pro Produktfamilie: i. d. R. ist GROUP_CODE stabil; wenn leer, ist GROUP_CODE_NAME meist vorhanden.
    // Falls dein Importer nur einen String akzeptiert: nimm 'GROUP_CODE_NAME'.
    'group_by'  => ['GROUP_CODE', 'GROUP_CODE_NAME'],

    /*
    |--------------------------------------------------------------------------
    | Basismapping: XML → interne Zielfelder
    |--------------------------------------------------------------------------
    | (Diese Keys sind "intern". Welche davon tatsächlich genutzt werden,
    | hängt an deinem Import-Pipeline-Stack.)
    */
    'fields' => [
        'product_number'     => ['ARTICLE'],
        'product_name'       => ['ARTICLE_NAME'],

        // HTML soll erhalten bleiben (Importer/Mapper entscheidet via normalize/allowHtml)
        'description'        => ['DESCRIPTION'],
        'short_description'  => ['SHORT_DESCRIPTION'],

        'ean'                => ['EAN'],

        // Zusatzinfos (falls DB-Felder existieren / später für Details nutzbar)
        'unit'               => ['UNIT'],
        'taric'              => ['TARIC'],
        'country_of_origin'  => ['COUNTRY_OF_ORIGIN'],
        'vat_rate'           => ['VAT_RATE'],
        'product_url'        => ['URL'],

        // Produkt-Flags
        'professional'       => ['PROFESSIONAL'],
        'sport'              => ['SPORT'],
        'grivel'             => ['GRIVEL'],
        'is_new'             => ['IS_NEW'],
        'is_sale'            => ['IS_SALE'],

        // Maße / technische Werte (viele sind je nach Artikel leer)
        'width'              => ['WIDTH'],
        'length'             => ['LENGTH'],
        'capacity'           => ['CAPACITY'],
        'volume'             => ['VOLUME'],
        'thickness'          => ['THICKNESS'],
        'size_range'         => ['SIZE_RANGE'],

        // Seil-/Hardware-Spezifika (optional, oft leer)
        'dynamic_elongation'        => ['DYNAMIC_ELONGATION'],
        'number_of_falls'           => ['NUMBER_OF_FALLS'],
        'diameter'                  => ['DIAMETER'],
        'shrinkage'                 => ['SHRINKAGE'],
        'knotability'               => ['KNOTABILITY'],
        'impact_force'              => ['IMPACT_FORCE'],
        'static_elongation'         => ['STATIC_ELONGATION'],
        'strength'                  => ['STRENGTH'],
        'strength_major_axis'       => ['STRENGTH_MAJOR_AXIS'],
        'strength_minor_axis'       => ['STRENGTH_MINOR_AXIS'],
        'strength_open_gate'        => ['STRENGTH_OPEN_GATE'],
        'gate_opening'              => ['GATE_OPENING'],
        'max_load'                  => ['MAX_LOAD'],
        'working_load'              => ['WORKING_LOAD'],
        'rope_diameter_for_hardware' => ['ROPE_DIAMETER_FOR_HARDWARE'],
        'bearing'                   => ['BEARING'],

        // Gewicht (z. B. "600 g • 21.2")
        'weight'             => ['WEIGHT'],

        // Material/Normen/Downloads
        'material'           => ['MATERIAL_COMPOSITION'],

        // Normen/Icons/IDs: können Mischformen sein (URLs/IDs)
        'norm_1'             => ['NORM1'],
        'norm_2'             => ['NORM2'],
        'norm_3'             => ['NORM3'],
        'norm_4'             => ['NORM4'],
        'norm_5'             => ['NORM5'],
        'norm_6'             => ['NORM6'],

        'instruction_url_1'  => ['DOWNLOAD_USER_MANUAL_1'],
        'instruction_url_2'  => ['DOWNLOAD_USER_MANUAL_2'],

        // Kategorien (Liste)
        'categories'         => ['CATEGORIES', 'CATEGORY'],

        // Bilder / Video
        'image_urls' => [
            'MAIN_PRODUCT_PICTURE',
            'PICTURE_2',
            'PICTURE_3',
            'PICTURE_4',
            'PICTURE_5',
            'PICTURE_6',
            'PICTURE_7',
            'PICTURE_8',
            'PICTURE_9',
            'PICTURE_10',
            'PICTURE_11',
            'PICTURE_12',
            'PICTURE_13',
        ],
        'video_urls' => ['DOWNLOAD_VIDEO'],

        // Varianten-Rohwerte
        'variety_colour'     => ['VARIETY_COLOUR'],
        'variety_size'       => ['VARIETY_SIZE'],

        // Fallback-Farbe (teils redundant, aber hilfreich)
        'colour'             => ['COLOUR'],

        'harness_size_table_html' => [
            'HARNESS_SIZE_TABLE_DESCRIPTION',
            'HARNESS_SIZE_UNI',
            'HARNESS_SIZE_K1',
            'HARNESS_SIZE_K2',
            'HARNESS_SIZE_XS',
            'HARNESS_SIZE_XSS',
            'HARNESS_SIZE_XSM',
            'HARNESS_SIZE_S',
            'HARNESS_SIZE_SL',
            'HARNESS_SIZE_M',
            'HARNESS_SIZE_ML',
            'HARNESS_SIZE_MXL',
            'HARNESS_SIZE_MXXL',
            'HARNESS_SIZE_L',
            'HARNESS_SIZE_LXXL',
            'HARNESS_SIZE_XL',
            'HARNESS_SIZE_XXL',
            'HARNESS_SIZE_XLXXL',
            'HARNESS_SIZE_XXXL',
        ],

        'product_type' => ['CATEGORIES', 'GROUP_CODE_NAME'],
    ],

    /*
    |--------------------------------------------------------------------------
    | ✅ Produkt-Mapping (das, was der Importer typischerweise aktiv nutzt)
    |--------------------------------------------------------------------------
    */
    'product' => [
        'product_number'     => ['ARTICLE'],
        'product_name'       => ['ARTICLE_NAME'],
        'description'        => ['DESCRIPTION'],
        'short_description'  => ['SHORT_DESCRIPTION'],

        'ean'                => ['EAN'],
        'weight'             => ['WEIGHT'],

        // wenn du nur ein Norm-Feld in der DB hast, nimm alle als Quelle (Importer nimmt i. d. R. das erste nicht-leere)
        'norm'               => ['NORM2', 'NORM1', 'NORM3', 'NORM4', 'NORM5', 'NORM6'],

        'material'           => ['MATERIAL_COMPOSITION'],

        // Primärer Manual-Link
        'instruction_url'    => ['DOWNLOAD_USER_MANUAL_1', 'DOWNLOAD_USER_MANUAL_2'],

        // Konformität/Produkt-URL (je nachdem was du im UI zeigen willst)
        'declaration_url'    => ['URL'],
    ],

    /*
    |--------------------------------------------------------------------------
    | SKU / Varianten-SKU
    |--------------------------------------------------------------------------
    */
    'sku' => ['ARTICLE'],

    // Varianten-SKU: ARTICLE[-VARIETY_COLOUR][-VARIETY_SIZE]
    'sku_compose' => [
        'fields'    => ['ARTICLE', 'VARIETY_COLOUR', 'VARIETY_SIZE'],
        'separator' => '-',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute (für Pivot / Woo-Attribute)
    |--------------------------------------------------------------------------
    | key = Anzeigename, value = Woo-Slug
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        // Variantenachsen
        'Farbe'               => 'pa_color',
        'Größe'               => 'pa_size',

        // Zusatz-Attribute
        'Produktgruppe'       => 'pa_group',
        'Produktgruppe Name'  => 'pa_group_name',
        'Kategorie'           => 'pa_category',

        'Einheit'             => 'pa_unit',
        'Herkunftsland'       => 'pa_country_of_origin',
        'TARIC'               => 'pa_taric',
        'VAT'                 => 'pa_vat_rate',

        // Flags
        'Professional'        => 'pa_professional',
        'Sport'               => 'pa_sport',
        'Grivel'              => 'pa_grivel',
        'Neu'                 => 'pa_is_new',
        'Sale'                => 'pa_is_sale',

        // Maße/Tech (optional)
        'Breite'              => 'pa_width',
        'Länge'               => 'pa_length',
        'Volumen'             => 'pa_volume',
        'Kapazität'           => 'pa_capacity',
        'Dicke'               => 'pa_thickness',
        'Größenrange'         => 'pa_size_range',

        // Normen (optional, wenn du sie als Attribute ausgeben willst)
        'Norm 1'              => 'pa_norm_1',
        'Norm 2'              => 'pa_norm_2',
        'Norm 3'              => 'pa_norm_3',
        'Norm 4'              => 'pa_norm_4',
        'Norm 5'              => 'pa_norm_5',
        'Norm 6'              => 'pa_norm_6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Varianten-Felder (assoziativ)
    |--------------------------------------------------------------------------
    */
    'variation_fields' => [
        // bevorzugt VARIETY_*; fallback auf COLOUR / SIZE_RANGE
        'color_name' => ['VARIETY_COLOUR', 'COLOUR'],
        'size_name'  => ['VARIETY_SIZE', 'SIZE_RANGE'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variation → Attributwerte (für Pivot/Optionen)
    |--------------------------------------------------------------------------
    */
    'variation' => [
        'Farbe' => ['VARIETY_COLOUR', 'COLOUR'],
        'Größe' => ['VARIETY_SIZE', 'SIZE_RANGE'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Medienzusammenführung
    |--------------------------------------------------------------------------
    */
    'images' => [
        'columns'  => [
            'MAIN_PRODUCT_PICTURE',
            'PICTURE_2',
            'PICTURE_3',
            'PICTURE_4',
            'PICTURE_5',
            'PICTURE_6',
            'PICTURE_7',
            'PICTURE_8',
            'PICTURE_9',
            'PICTURE_10',
            'PICTURE_11',
            'PICTURE_12',
            'PICTURE_13',
        ],
        'split_on' => [',', ';', '|'],
        'validate' => true,
        'dedupe'   => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Normalisierungen / Umrechnungen
    |--------------------------------------------------------------------------
    | (Wenn du später eine eigene SingingRock\Map-Klasse baust: hier tauschen.)
    */
    'transforms' => [
        'product_name'              => [\App\Support\Import\SingingRock\Map::class, 'productName'],
        'description'               => [\App\Support\Import\SingingRock\Map::class, 'description'],
        'short_description'         => [\App\Support\Import\SingingRock\Map::class, 'description'],
        'ean'                       => [\App\Support\Import\SingingRock\Map::class, 'ean'],
        'weight'                    => [\App\Support\Import\SingingRock\Map::class, 'weightGrams'],
        'image_urls'                => [\App\Support\Import\SingingRock\Map::class, 'imageUrls'],
        'video_urls'                => [\App\Support\Import\SingingRock\Map::class, 'videoUrls'],
        'professional'              => [\App\Support\Import\SingingRock\Map::class, 'toBool'],
        'sport'                     => [\App\Support\Import\SingingRock\Map::class, 'toBool'],
        'grivel'                    => [\App\Support\Import\SingingRock\Map::class, 'toBool'],
        'harness_size_table_html'   => [\App\Support\Import\SingingRock\Map::class, 'harnessSizeTableHtml'],
        'strength_major_axis'       => [\App\Support\Import\SingingRock\Map::class, 'forceDisplay'],
        'strength_minor_axis'       => [\App\Support\Import\SingingRock\Map::class, 'forceDisplay'],
        'strength_open_gate'        => [\App\Support\Import\SingingRock\Map::class, 'forceDisplay'],
        'gate_opening'              => [\App\Support\Import\SingingRock\Map::class, 'mmInt'],
        'product_type'              => [\App\Support\Import\SingingRock\Map::class, 'productType'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import-Regeln
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'short_desc_from_first_sentence_if_missing' => true,
        'ignore_prices' => true,
        'translate_to_de' => false,
    ],
];
