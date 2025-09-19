<?php

/**
 * WooCommerce Konfiguration
 *
 * - API-Einstellungen für ausgehende REST-Calls
 * - Rate-Limiting (Requests pro Minute)
 * - Sync-Optionen (z.B. Pagination)
 * - Mappings/Defaults für Varianten-Payloads (ohne Preisfelder)
 */

return [

    // Globale Schalter
    'sync_enabled'        => env('WOO_SYNC_ENABLED', false),

    // Woo API Version, z.B. 'wc/v3'
    'default_api_version' => env('WOO_API_VERSION', 'wc/v3'),

    // Basis-API-Zugang
    'api' => [
        // Ohne abschließenden Slash, z.B. 'https://shop.example.com'
        'base_url' => env('WOO_API_BASE_URL', 'https://shop.example.com'),
        'key'      => env('WOO_API_KEY'),
        'secret'   => env('WOO_API_SECRET'),
    ],

    // Rate-Limiter (einfacher Sleep pro Request, siehe VariationSyncService)
    'rate_limit' => [
        'rpm'   => env('WOO_RATE_LIMIT_RPM', 100), // Requests pro Minute
        'burst' => env('WOO_RATE_LIMIT_BURST', 40),
    ],

    // Sync-spezifische Optionen
    'sync' => [
        'variations' => [
            // Page-Size beim Laden existierender Woo-Varianten
            'per_page' => env('WOO_SYNC_VARIATIONS_PER_PAGE', 100),
        ],
    ],

    // Mappings/Defaults für Varianten-Payloads
    'mapping' => [
        // Interne Feldnamen -> Woo-Feldnamen
        'variation_field_map' => [
            // Preise bewusst NICHT enthalten
            'sku'            => 'sku',
            'stock_quantity' => 'stock_quantity',
            'weight'         => 'weight', // als String an Woo
            // 'ean' wird als meta_data gesendet (siehe Builder)
        ],

        // Interne Attribut-Keys -> Woo-Attributnamen
        'variation_attribute_map' => [
            'size'          => 'Size',
            'color'         => 'Color',
            'length'        => 'Length',
            'certification' => 'Certification',
        ],

        // Defaultwerte für Varianten
        'variation_defaults' => [
            // Standard: Lagerführung aktiv; stock_status wird aus Menge abgeleitet
            'manage_stock' => (bool) env('WOO_VAR_DEFAULT_MANAGE_STOCK', true),
            // Falls keine Menge vorhanden ist, initial nicht blockieren
            'stock_status' => env('WOO_VAR_DEFAULT_STOCK_STATUS', 'instock'),
        ],
    ],

];
