<?php

use App\Models\Product;
use App\Services\ProductNaming\ProductKind;
use App\Services\ProductNaming\ProductNameContext;

it('resolves variable products as variable regardless of variation count', function () {
    $product = new Product([
        'product_type' => 'variable',
    ]);

    expect(ProductNameContext::resolveKind($product))
        ->toBe(ProductKind::Variable);
});

it('resolves sets as set', function () {
    $product = new Product([
        'product_type' => 'set',
    ]);

    expect(ProductNameContext::resolveKind($product))
        ->toBe(ProductKind::Set);
});

it('resolves other product types as simple', function () {
    $product = new Product([
        'product_type' => 'simple',
    ]);

    expect(ProductNameContext::resolveKind($product))
        ->toBe(ProductKind::Simple);
});
