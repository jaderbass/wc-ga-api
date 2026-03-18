<?php

namespace App\Services\ProductNaming;

use App\Models\ProductVariation;

final class VariationDisplayNameResolver
{
    public function __construct(
        private readonly DefaultProductNameBuilder $builder,
    ) {}

    public function resolve(ProductVariation $variation): string
    {
        $ctx = ProductNameContext::fromVariation($variation);

        return $this->builder->build($ctx)->productName
            ?: (string) ($variation->sku ?? '—');
    }
}
