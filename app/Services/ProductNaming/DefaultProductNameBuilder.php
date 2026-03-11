<?php

namespace App\Services\ProductNaming;

/**
 * Builds the Woo product name according to customer rules.
 *
 * Customer rules (summary):
 * - Manufacturer name in CAPS
 * - Designation with only first letter uppercased
 * - Parts separated by " - "
 * - Simple: Manufacturer - Category - Designation - P1 - P2 - P3
 * - Variable/Set: Manufacturer - Category - Designation - P1
 *
 * Notes:
 * - The builder does NOT decide category or property order.
 * - Empty parts are removed automatically to avoid duplicate separators.
 */
final class DefaultProductNameBuilder
{
  public function __construct(
    private readonly NameTemplateRegistry $registry,
  ) {}

  public function build(ProductNameContext $ctx): ProductNameResult
  {
    $tpl = $this->registry->get($ctx);
    $separator = (string) $tpl['separator'];
    $template = (array) $tpl['template'];

    $p1 = $ctx->properties[0] ?? null;
    $p2 = $ctx->properties[1] ?? null;
    $p3 = $ctx->properties[2] ?? null;

    /** @var array<string, string|null> $tokens */
    $tokens = [
      'manufacturer' => $this->normalizeManufacturer($ctx->manufacturerName),
      'category'     => $this->normalizePart($ctx->categoryName),
      'designation' => $this->normalizeDesignation(
        $ctx->designation,
        $ctx->manufacturerName
      ),
      'p1'           => $this->normalizePart($p1),
      'p2'           => $this->normalizePart($p2),
      'p3'           => $this->normalizePart($p3),
    ];

    $parts = [];
    foreach ($template as $token) {
      $val = $tokens[$token] ?? null;
      if ($val !== null && $val !== '') {
        $parts[] = $val;
      }
    }

    $name = $this->joinAndCleanup($parts, $separator);

    return new ProductNameResult(productName: $name, parts: $parts);
  }

  /**
   * Manufacturer must be uppercased.
   */
  private function normalizeManufacturer(string $name): string
  {
    $name = trim($name);
    return $name === '' ? '' : mb_strtoupper($name);
  }

  /**
   * Customer: "always first initial letter uppercased".
   *
   * Important:
   * - We do NOT title-case the entire string to avoid breaking model/brand names.
   * - We only uppercase the first character and keep the rest as-is.
   */
  private function normalizeDesignation(string $designation, string $manufacturer): string
  {
    $designation = $this->removeManufacturerPrefix($designation, $manufacturer);
    $designation = trim($designation);

    if ($designation === '') {
      return '';
    }

    $first = mb_substr($designation, 0, 1);
    $rest  = mb_substr($designation, 1);

    return mb_strtoupper($first) . $rest;
  }

  /**
   * Normalizes a generic part (category/properties).
   *
   * Empty strings become null to make part filtering easier.
   */
  private function normalizePart(?string $value): ?string
  {
    if ($value === null) {
      return null;
    }

    $v = trim($value);
    return $v === '' ? null : $v;
  }

  /**
   * Joins parts and cleans whitespace.
   *
   * @param array<int, string> $parts
   */
  private function joinAndCleanup(array $parts, string $separator): string
  {
    $name = implode($separator, $parts);

    // Normalize whitespace
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return trim($name);
  }

  /**
   * Removes duplicated manufacturer prefix from designation.
   *
   * Example:
   * "ALIENS Aufreissfalldämpfer Reactor Rope"
   * -> "Aufreissfalldämpfer Reactor Rope"
   */
  private function removeManufacturerPrefix(string $designation, string $manufacturer): string
  {
    $designation = trim($designation);
    $manufacturer = trim($manufacturer);

    if ($designation === '' || $manufacturer === '') {
      return $designation;
    }

    $quoted = preg_quote($manufacturer, '/');

    $updated = preg_replace(
      '/^' . $quoted . '\b(?:\s*[-–—:|]\s*|\s+)/iu',
      '',
      $designation,
      1
    );

    return is_string($updated) ? trim($updated) : $designation;
  }
}
