<?php

namespace App\Support\Woo;

class WritePolicy
{
  protected array $neverWrite = [];
  protected array $manufacturerAllow = [];
  protected array $legacyAttributes = [];

  public static function fromArray(array $data): self
  {
    $p = new self();
    $p->neverWrite        = array_fill_keys($data['never_write'] ?? [], true);
    $p->manufacturerAllow = $data['manufacturer_allow'] ?? [];
    $p->legacyAttributes  = array_fill_keys($data['legacy_attributes'] ?? [], true);
    return $p;
  }

  public static function fromConfig(): self
  {
    return self::fromArray(config('woo_policy', []));
  }

  public function isWritable(string $wcField, string $manufacturer): bool
  {
    $f = trim($wcField);
    if (isset($this->neverWrite[$f])) return false;
    if (isset($this->legacyAttributes[$f])) return false;

    $allow = $this->manufacturerAllow[$f] ?? null;
    if (is_array($allow)) {
      $key = strtolower(str_replace([' ', '-'], '_', $manufacturer));
      if (array_key_exists($key, $allow)) return (bool)$allow[$key];
    }
    return true; // Default: erlauben
  }

  public function filterPayload(array $payload, string $manufacturer): array
  {
    $out = [];
    foreach ($payload as $k => $v) {
      if ($this->isWritable($k, $manufacturer)) $out[$k] = $v;
    }
    return $out;
  }
}
