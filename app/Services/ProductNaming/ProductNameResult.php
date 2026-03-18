<?php

namespace App\Services\ProductNaming;

/**
 * Result object of the ProductNameBuilder.
 *
 * `parts` is optional and helpful for logging/debugging,
 * especially when customer reports "name looks wrong".
 */
final class ProductNameResult
{
    /**
     * @param array<int, string> $parts
     * @param array<string, string|null> $tokens
     */
    public function __construct(
        public readonly string $productName,
        public readonly array $parts = [],
        public readonly array $tokens = [],
    ) {}
}
