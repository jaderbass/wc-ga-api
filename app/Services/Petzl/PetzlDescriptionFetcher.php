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

        file_put_contents(
            storage_path('app/petzl-debug-full.html'),
            $html
        );

        return [
            'url' => $url,
            'description_html' => $this->extractDescriptionHtml($html),
            'hash' => sha1($html),
        ];
    }

    protected function extractDescriptionHtml(string $html): string
    {
        $crawler = new Crawler($html);

        $nodes = $crawler->filter('#descriptif');

        if ($nodes->count() === 0) {
            throw new RuntimeException('Petzl description block "#descriptif" not found.');
        }

        return $this->cleanHtml(
            trim($nodes->first()->html())
        );
    }

    protected function cleanHtml(string $html): string
    {
        $html = str_replace('<br />-', '<br>- ', $html);
        $html = preg_replace('/\sclass="[^"]*"/', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
