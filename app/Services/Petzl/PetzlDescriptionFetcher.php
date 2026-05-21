<?php

namespace App\Services\Petzl;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class PetzlDescriptionFetcher
{
    public function fetchFromUrl(string $url): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'GeoAlpin Product Importer',
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->timeout(20)
            ->retry(2, 1000)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Petzl request failed with status {$response->status()}.");
        }

        $html = $response->body();

        return [
            'url' => $url,
            'description_html' => $this->extractDescriptionHtml($html),
            'hash' => sha1($html),
        ];
    }

    protected function extractDescriptionHtml(string $html): string
    {
        $crawler = new Crawler($html);

        $selectors = [
            '[data-testid="product-description"]',
            '.product-description',
            '.product__description',
            '.description',
            'main',
        ];

        foreach ($selectors as $selector) {
            $nodes = $crawler->filter($selector);

            if ($nodes->count() > 0) {
                return trim($nodes->first()->html());
            }
        }

        throw new RuntimeException('No matching Petzl description container found.');
    }
}
