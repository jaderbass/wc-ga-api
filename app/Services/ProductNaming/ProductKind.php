<?php

namespace App\Services\ProductNaming;

/**
 * Defines which customer naming rule applies.
 *
 * - Simple: single Woo product without variations
 * - Variable: parent product with variations
 * - Set: "set" product type (treated like variable per customer spec)
 */
enum ProductKind: string
{
  case Simple = 'simple';
  case Variable = 'variable';
  case Set = 'set';
}
