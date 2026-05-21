<?php

namespace App\Services\Petzl;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Ruft Petzl-Produktseiten ab und extrahiert die Beschreibungsinhalte.
 */
class PetzlDescriptionFetcher
{
    /**
     * Ruft eine Petzl-Produktseite ab und gibt die bereinigte Beschreibung zurück.
     *
     * @param string $url Vollständige URL zur Petzl-Produktseite.
     *
     * @throws RuntimeException Wenn der Abruf fehlschlägt oder kein Beschreibungsblock gefunden wird.
     *
     * @return array{url: string, description_html: string, hash: string}
     */
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

    /**
     * Extrahiert den Beschreibungsblock aus dem Petzl-HTML.
     *
     * @param string $html Vollständiges HTML der Produktseite.
     *
     * @throws RuntimeException Wenn der Beschreibungsblock nicht gefunden wird.
     */
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

    /**
     * Bereinigt Petzl-spezifisches HTML für die spätere WooCommerce-Nutzung.
     */
    protected function cleanHtml(string $html): string
    {
        $html = str_replace('<br />-', '<br>- ', $html);
        $html = preg_replace('/\sclass="[^"]*"/', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
