<?php

namespace App\Services\Petzl;

use App\Models\Product;

class PetzlDescriptionImportService
{
    public function __construct(
        protected PetzlDescriptionFetcher $fetcher,
    ) {}

    public function importFromUrl(Product $product, string $url): array
    {
        $result = $this->fetcher->fetchFromUrl($url);

        $product->forceFill([
            'petzl_description_html' => $result['description_html'],
            'petzl_description_source_url' => $result['url'],
            'petzl_description_fetched_at' => now(),
            'petzl_description_hash' => $result['hash'],
            'description_source' => $product->description_source === 'manual'
                ? 'manual'
                : 'auto',
        ])->save();

        return $result;
    }
}
