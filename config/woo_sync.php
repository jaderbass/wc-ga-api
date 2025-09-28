<?php

return [
    'allow_sku_write_back'   => env('WOO_ALLOW_SKU_WRITE_BACK', false),
    'variant_requires_sku'   => env('WOO_VARIANT_REQUIRES_SKU', true),
    'parent_must_no_sku'     => env('WOO_PARENT_MUST_NOT_HAVE_SKU', true),
];
