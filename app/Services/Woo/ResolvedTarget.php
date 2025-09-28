<?php

namespace App\Services\Woo;

/**
 * DTO für Resolver-Ergebnisse.
 */
class ResolvedTarget
{
    public function __construct(
        public readonly ?int $woo_product_id,
        public readonly ?int $woo_variation_id,
        public readonly string $match_type,
        public readonly int $confidence,
        public readonly bool $notFound = false,
        public readonly bool $conflict = false,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            $a['woo_product_id'] ?? null,
            $a['woo_variation_id'] ?? null,
            $a['match_type'] ?? 'UNKNOWN',
            $a['confidence'] ?? 0,
            false,
            false
        );
    }

    public static function notFound(): self
    {
        return new self(null, null, 'NOT_FOUND', 0, true, false);
    }

    public static function conflict(string $type, string $value): self
    {
        return new self(null, null, "CONFLICT_{$type}", 0, false, true);
    }
}
