<?php

/**
 * Product naming configuration.
 *
 * The customer wants Woo names in the format:
 *   MANUFACTURER (CAPS) - Category - Designation (first letter uppercased) - Property1 - ...
 *
 * We define a token order per product kind (simple/variable/set).
 * Builder takes already prepared values (category + properties in correct order).
 */
return [

  /**
   * Default rule set (used if no manufacturer-specific override exists).
   */
  'default' => [
    /**
     * Separator between parts - must be exactly " - " according to customer rules.
     */
    'separator' => ' - ',

    /**
     * Simple product:
     * Manufacturer - Category - Designation - Property1 - Property2 - Property3
     */
    'simple'   => ['manufacturer', 'category', 'designation', 'p1', 'p2', 'p3'],

    /**
     * Variable product:
     * Manufacturer - Category - Designation - Property1
     */
    'variable' => ['manufacturer', 'category', 'designation', 'p1'],

    /**
     * Set:
     * Same as variable in customer spec.
     */
    'set'      => ['manufacturer', 'category', 'designation', 'p1'],
  ],

  /**
   * Optional manufacturer-specific overrides (keyed by manufacturer_id).
   *
   * Example:
   * 'manufacturers' => [
   *   1 => [
   *     'separator' => ' - ',
   *     'simple' => [...],
   *   ],
   * ],
   */
  'manufacturers' => [],
];
