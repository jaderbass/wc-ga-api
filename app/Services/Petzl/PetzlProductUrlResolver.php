<?php

namespace App\Services\Petzl;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            ->trim('-')
            ->toString();

        $paths = [
            'Verbindungsmittel-und-Falldampfer',
            'Seilklemmen',
            'Seilklemmen-fuer-den-Aufstieg-am-Seil',
            'Helme',
            'Gurte',
            'Auffang--und-Haltegurte',
            'Gurte-zum-Arbeiten-am-Seil',
            'Karabiner-und-Verbindungselemente',
            'Seile',
            'Abseilgeraete',
            'Rollen',
            'Anschlageinrichtungen',
            'Rettung',
            'Transporttaschen',
            'Stirnlampen',
            'Zubehoer-fuer-Stirnlampen',
            'Zubehoer',
        ];

        $slugCandidates = $this->buildSlugCandidates($slug);

        Log::info('Petzl slug candidates.', [
            'slug' => $slug,
            'slug_candidates' => $slugCandidates->all(),
        ]);

        $markets = [
            'Professional',
            'Sport',
        ];

        $candidateUrls = collect($markets)
            ->flatMap(
                fn(string $market) => collect($paths)->flatMap(
                    fn(string $path) => $slugCandidates->map(
                        fn(string $slugCandidate) =>
                        "https://www.petzl.com/DE/de/{$market}/{$path}/{$slugCandidate}"
                    )
                )
            )
            ->all();

        Log::info('Petzl resolver candidate URLs.', [
            'product_name' => $productName,
            'slug' => $slug,
            'candidate_urls' => $candidateUrls,
        ]);

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
                && str_contains($response->body(), 'id="descriptif"')
            ) {
                Log::info('Checking Petzl URL.', [
                    'url' => $url,
                ]);
                return $url;
            }
        }

        Log::warning('Petzl URL resolver failed.', [
            'product_name' => $productName,
            'slug' => $slug,
            'candidate_urls' => $candidateUrls,
        ]);

        throw new RuntimeException(
            "No Petzl product URL found for product name {$productName}."
        );
    }

    /**
     * Erstellt mögliche Petzl-URL-Slugs aus einem Produktnamen-Slug.
     *
     * Einige Petzl-Zubehörartikel besitzen keine eigene Detailseite, sondern
     * verweisen sinnvoll auf die Seite des Hauptprodukts.
     *
     * @param string $slug Normalisierter Produktnamen-Slug.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    protected function buildSlugCandidates(string $slug): \Illuminate\Support\Collection
    {
        $candidates = [
            $slug,

            str_replace('-CLICK-WEBBING-STRAP', '-CLICK', $slug),
            str_replace('-WEBBING-STRAP', '', $slug),
            str_replace('-CLICK', '', $slug),
            str_replace('CATCH-FOR-', '', $slug),

            str_replace('-EUROPEAN-VERSION', '', $slug),
            str_replace('-INTERNATIONAL-VERSION', '', $slug),
            str_replace('-BEFORE-2019', '', $slug),
            str_replace('-BEFORE-2020', '', $slug),

            str_replace('SEAT-FOR-', '', $slug),
            str_replace('-HARNESSES', '', $slug),
            str_replace('-HARNESS', '', $slug),

            str_replace('SHOULDER-STRAPS-FOR-', '', $slug),
            str_replace('ATTACHMENT-BRIDGE-FOR-', '', $slug),
            str_replace('FOOT-LOOP-FOR-', '', $slug),
            str_replace('ELASTIC-BAND-FOR-', '', $slug),

            str_replace('-EUROPEAN-VERSION', '-EUROPÄISCHE-AUSFÜHRUNG', $slug),
            str_replace('-INTERNATIONAL-VERSION', '-INTERNATIONALE-AUSFÜHRUNG', $slug),
        ];

        if (str_contains($slug, 'PANTIN')) {
            $candidates[] = 'PANTIN';
            $candidates[] = 'PANTIN-CLICK';
        }

        if (str_contains($slug, 'KNEE-ASCENT')) {
            $candidates[] = 'KNEE-ASCENT-KIT';
            $candidates[] = 'KNEE-ASCENT';
        }

        if (str_contains($slug, 'VERTEX')) {
            $candidates[] = 'VERTEX';
            $candidates[] = 'VERTEX-VENT';
        }

        if (str_contains($slug, 'STRATO')) {
            $candidates[] = 'STRATO';
            $candidates[] = 'STRATO-VENT';
        }

        if (str_contains($slug, 'SEQUOIA')) {
            $candidates[] = 'SEQUOIA';
            $candidates[] = 'SEQUOIA-SRT';
        }

        if (str_contains($slug, 'AVAO')) {
            $candidates[] = 'AVAO';
            $candidates[] = 'AVAO-FAST';
        }

        if (str_contains($slug, 'VOLT')) {
            $candidates[] = 'VOLT';
            $candidates[] = 'VOLT-WIND';
            $candidates[] = 'VOLT-LIGHT';
        }

        if (str_contains($slug, 'NEWTON')) {
            $candidates[] = 'NEWTON';
            $candidates[] = 'NEWTON-FAST';
            $candidates[] = 'NEWTON-EASYFIT';
        }

        if (str_contains($slug, 'ASTRO')) {
            $candidates[] = 'ASTRO';
        }

        return collect($candidates)
            ->filter()
            ->unique()
            ->values();
    }
}
