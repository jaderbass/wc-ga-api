<?php

namespace App\Services\Petzl;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Ermittelt die URL einer Petzl-Produktseite.
 *
 * Aktuell erfolgt die Auflösung primär über den Produktnamen, da die
 * Referenzsuche auf petzl.com serverseitig keine stabilen Ergebnisse liefert.
 */
class PetzlProductUrlResolver
{
    /**
     * Versucht eine Petzl-Produktseite über die Referenznummer zu finden.
     *
     * Hinweis:
     * Die Petzl-Suche liefert serverseitig aktuell keine stabilen Treffer für
     * Referenzen wie "L011AB00". Diese Methode bleibt vorerst als experimenteller
     * Fallback erhalten, sollte aber nicht für produktive Imports verwendet werden.
     */
    public function resolveByReference(string $reference): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'GeoAlpin Product Importer',
        ])
            ->timeout(20)
            ->retry(2, 1000)
            ->get('https://www.petzl.com/DE/de/Suche', [
                'text' => $reference,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Petzl search request failed.');
        }

        file_put_contents(
            storage_path('app/petzl-search-debug.html'),
            $response->body()
        );

        $crawler = new Crawler($response->body());

        $links = $crawler->filter('a[href*="/DE/de/Professional/"]')
            ->each(function (Crawler $node) {
                return $node->attr('href');
            });

        $links = collect($links)
            ->filter()
            ->map(function (string $href) {
                return str_starts_with($href, 'http')
                    ? $href
                    : 'https://www.petzl.com' . $href;
            })
            ->unique()
            ->values();

        if ($links->isEmpty()) {
            throw new RuntimeException(
                "No Petzl product candidates found for reference {$reference}."
            );
        }

        foreach ($links as $url) {
            $productResponse = Http::withHeaders([
                'User-Agent' => 'GeoAlpin Product Importer',
            ])
                ->timeout(20)
                ->retry(2, 1000)
                ->get($url);

            if (
                $productResponse->successful()
                && str_contains($productResponse->body(), $reference)
            ) {
                return $url;
            }
        }

        throw new RuntimeException(
            "No Petzl product URL found for reference {$reference}."
        );
    }

    /**
     * Ermittelt eine Petzl-Produkt-URL anhand des Produktnamens.
     *
     * Der Produktname wird in einen URL-Slug umgewandelt und gegen bekannte
     * Petzl-URL-Strukturen geprüft.
     *
     * @param string $productName Produktbezeichnung aus Importdaten.
     *
     * @throws RuntimeException Wenn keine passende URL gefunden wurde.
     *
     * @return string Vollständige URL zur Petzl-Produktseite.
     */
    public function resolveByProductName(string $productName): string
    {
        $slug = str($productName)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-');

        $paths = [
            'Verbindungsmittel-und-Falldampfer',
            'Helme',
            'Gurte',
            'Karabiner-und-Verbindungselemente',
            'Seile',
        ];

        $candidateUrls = collect($paths)
            ->map(
                fn(string $path) =>
                "https://www.petzl.com/DE/de/Professional/{$path}/{$slug}"
            )
            ->all();

        foreach ($candidateUrls as $url) {
            $response = Http::withHeaders([
                'User-Agent' => 'GeoAlpin Product Importer',
            ])
                ->timeout(20)
                ->retry(2, 1000)
                ->get($url);

            if (
                $response->successful()
                && ! str_contains($response->body(), 'Page introuvable')
                && ! str_contains($response->body(), '404')
            ) {
                return $url;
            }
        }

        throw new RuntimeException(
            "No Petzl product URL found for product name {$productName}."
        );
    }
}
