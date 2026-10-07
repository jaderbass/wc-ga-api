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
     * Ruft eine Petzl-Produktseite ab und gibt die bereinigten Beschreibungen zurück.
     *
     * @param string $url Vollständige URL zur Petzl-Produktseite.
     *
     * @throws RuntimeException Wenn der Abruf fehlschlägt oder kein Beschreibungsblock gefunden wird.
     *
     * @return array{
     *     url: string,
     *     description_html: string,
     *     short_description_html: string|null,
     *     hash: string
     * }
     */
    public function fetchFromUrl(string $url): array
    {
        $this->assertAllowedPetzlUrl($url);

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
            'short_description_html' => $this->extractShortDescriptionHtml($html),
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

        $selectors = [
            '#descriptif',
            '#description',
            '[id*="descript"]',
            '[class*="description"]',
            '[class*="product-description"]',
        ];

        foreach ($selectors as $selector) {
            $nodes = $crawler->filter($selector);

            if ($nodes->count() > 0) {
                return $this->cleanHtml(
                    trim($nodes->first()->html())
                );
            }
        }

        throw new RuntimeException(
            'Petzl description block not found. Tried selectors: ' . implode(', ', $selectors)
        );
    }

    /**
     * Extrahiert die Petzl-Kurzbeschreibung aus der Produktseite.
     *
     * Die Kurzbeschreibung besteht – sofern vorhanden – aus:
     * - .productSubtitle
     * - .productCaracteristiquesPosition
     *
     * Fehlen beide Elemente, wird null zurückgegeben.
     */
    protected function extractShortDescriptionHtml(string $html): ?string
    {
        $crawler = new Crawler($html);

        $parts = [];

        $subtitleNodes = $crawler->filter('.productSubtitle');

        if ($subtitleNodes->count() > 0) {
            $subtitle = trim($subtitleNodes->first()->text());

            if ($subtitle !== '') {
                $parts[] = '<p>' . e($subtitle) . '</p>';
            }
        }

        $characteristicsNodes = $crawler->filter('.productCaracteristiquesPosition');

        if ($characteristicsNodes->count() > 0) {
            $characteristics = trim($characteristicsNodes->first()->text());

            if ($characteristics !== '') {
                $parts[] = '<p>' . e($characteristics) . '</p>';
            }
        }

        if ($parts === []) {
            return null;
        }

        return $this->cleanHtml(
            implode('', $parts)
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

    /**
     * Stellt sicher, dass ausschließlich HTTPS-URLs von petzl.com abgerufen werden.
     */
    protected function assertAllowedPetzlUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Ungültige Petzl-URL.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        $isPetzlHost = $host === 'petzl.com'
            || str_ends_with($host, '.petzl.com');

        if ($scheme !== 'https' || ! $isPetzlHost) {
            throw new RuntimeException(
                'Es sind nur HTTPS-URLs von petzl.com zulässig.'
            );
        }
    }
}
