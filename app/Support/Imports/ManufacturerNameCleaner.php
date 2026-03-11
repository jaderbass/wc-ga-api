<?php

namespace App\Support\Imports;

/**
 * Bereinigt Produktnamen um Hersteller-Präfixe oder isolierte Hersteller-Zusätze,
 * damit im finalen Produktnamen kein doppelter Hersteller erscheint.
 *
 * Beispiele:
 * - "ALIENS Aufreissfalldämpfer Reactor Rope" => "Aufreissfalldämpfer Reactor Rope"
 * - "Aliens - Reactor Rope" => "Reactor Rope"
 * - "Reactor Rope - ALIENS" => "Reactor Rope"
 * - "Reactor Rope (ALIENS)" => "Reactor Rope"
 *
 * Bewusst NICHT aggressiv:
 * - "Super Aliens Cam Set" bleibt unverändert
 */
class ManufacturerNameCleaner
{
  /**
   * Bereinigt den Produktnamen anhand des Herstellernamens
   * und optionaler Alias-Schreibweisen.
   *
   * @param string $name
   * @param string $manufacturerName
   * @param array<int, string> $aliases
   * @return string
   */
  public function clean(string $name, string $manufacturerName, array $aliases = []): string
  {
    $name = $this->normalizeWhitespace($name);

    if ($name === '') {
      return $name;
    }

    $tokens = $this->buildTokens($manufacturerName, $aliases);

    if ($tokens === []) {
      return $name;
    }

    $name = $this->removeLeadingManufacturerToken($name, $tokens);
    $name = $this->removeTrailingManufacturerToken($name, $tokens);
    $name = $this->removeBracketedManufacturerToken($name, $tokens);
    $name = $this->cleanupSeparators($name);

    return trim($name, " \t\n\r\0\x0B-–—:|,;");
  }

  /**
   * @param string $manufacturerName
   * @param array<int, string> $aliases
   * @return array<int, string>
   */
  protected function buildTokens(string $manufacturerName, array $aliases = []): array
  {
    $tokens = array_merge([$manufacturerName], $aliases);

    $tokens = array_map(
      static fn(string $value): string => trim($value),
      $tokens
    );

    $tokens = array_filter(
      $tokens,
      static fn(string $value): bool => $value !== ''
    );

    $tokens = array_values(array_unique($tokens));

    usort(
      $tokens,
      static fn(string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a)
    );

    return $tokens;
  }

  protected function normalizeWhitespace(string $value): string
  {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);

    return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
  }

  /**
   * Entfernt Hersteller nur am Anfang, z. B.:
   * - ALIENS Produkt
   * - ALIENS - Produkt
   * - Aliens: Produkt
   */
  protected function removeLeadingManufacturerToken(string $name, array $tokens): string
  {
    foreach ($tokens as $token) {
      $quoted = preg_quote($token, '/');

      $patterns = [
        '/^\s*' . $quoted . '\b(?:\s*[-–—:|]\s*|\s+)/iu',
        '/^\s*' . $quoted . '\s*$/iu',
      ];

      foreach ($patterns as $pattern) {
        $updated = preg_replace($pattern, '', $name, 1);

        if (is_string($updated) && $updated !== $name) {
          return trim($updated);
        }
      }
    }

    return $name;
  }

  /**
   * Entfernt isolierten Hersteller am Ende, z. B.:
   * - Produkt - ALIENS
   * - Produkt | Aliens
   */
  protected function removeTrailingManufacturerToken(string $name, array $tokens): string
  {
    foreach ($tokens as $token) {
      $quoted = preg_quote($token, '/');

      $pattern = '/(?:\s*[-–—:|]\s*|\s+)' . $quoted . '\s*$/iu';
      $updated = preg_replace($pattern, '', $name, 1);

      if (is_string($updated) && $updated !== $name) {
        return trim($updated);
      }
    }

    return $name;
  }

  /**
   * Entfernt isolierte Klammer-Zusätze, z. B.:
   * - Produkt (ALIENS)
   * - Produkt [Aliens]
   */
  protected function removeBracketedManufacturerToken(string $name, array $tokens): string
  {
    foreach ($tokens as $token) {
      $quoted = preg_quote($token, '/');

      $patterns = [
        '/\(\s*' . $quoted . '\s*\)/iu',
        '/\[\s*' . $quoted . '\s*\]/iu',
      ];

      foreach ($patterns as $pattern) {
        $updated = preg_replace($pattern, '', $name, 1);

        if (is_string($updated) && $updated !== $name) {
          $name = trim($updated);
        }
      }
    }

    return $name;
  }

  protected function cleanupSeparators(string $name): string
  {
    $name = preg_replace('/\s*[-–—:|]{2,}\s*/u', ' - ', $name) ?? $name;
    $name = preg_replace('/\s{2,}/u', ' ', $name) ?? $name;

    return trim($name);
  }
}
