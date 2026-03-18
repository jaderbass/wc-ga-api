<?php

namespace App\Services\ProductNaming;

final class ProductNameResult
{
    /**
     * @param array<int, string> $parts
     * @param array<string, string|null> $tokens
     * @param array<int, string> $template
     */
    public function __construct(
        public readonly string $productName,
        public readonly array $parts = [],
        public readonly array $tokens = [],
        public readonly array $template = [],
        public readonly string $separator = ' - ',
    ) {}
}
