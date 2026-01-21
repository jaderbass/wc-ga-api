<?php

namespace App\Services\ProductNaming;

/**
 * Loads naming templates for a given manufacturer/product kind.
 *
 * Source is config/product_naming.php for now, but the registry can later
 * be backed by DB/Filament UI without changing the builder.
 */
final class NameTemplateRegistry
{
  /**
   * @return array{separator:string, template:array<int, string>}
   */
  public function get(ProductNameContext $ctx): array
  {
    /** @var array $cfg */
    $cfg = config('product_naming', []);

    $default = $cfg['default'] ?? [];
    $mfg = $ctx->manufacturerId !== null
      ? (($cfg['manufacturers'][$ctx->manufacturerId] ?? null) ?: null)
      : null;

    $separator = (string) ($mfg['separator'] ?? $default['separator'] ?? ' - ');

    $key = $ctx->kind->value;
    $template = $mfg[$key] ?? $default[$key] ?? ['manufacturer', 'category', 'designation'];

    return [
      'separator' => $separator,
      'template'  => array_values((array) $template),
    ];
  }
}
