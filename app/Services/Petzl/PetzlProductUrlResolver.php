<?php

namespace App\Services\Petzl;

use App\Models\PetzlCategoryMapping;
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
    public function resolveByProductName(
        string $productName,
        ?string $sourceCategory = null,
        ?string $sourceSubcategory = null
    ): string {
        $normalizedProductName = $this->normalizeProductName($productName);

        $slug = str($normalizedProductName)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        $paths = $this->resolvePaths($sourceCategory, $sourceSubcategory);

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

            $responseBody = $response->body();

            if (
                $response->successful()
                && ! str_contains($responseBody, 'Page introuvable')
                && ! str_contains($responseBody, 'jsonPageStructure')
                && str_contains($responseBody, 'id="descriptif"')
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
     * Ermittelt mögliche Petzl-Pfade aus dem Kategorie-Mapping.
     *
     * Wenn ein gepflegter Mapping-Pfad vorhanden ist, wird nur dieser Pfad
     * verwendet. Die statischen Fallback-Pfade greifen nur ohne Mapping.
     *
     * @return array<int, string>
     */
    protected function resolvePaths(
        ?string $sourceCategory,
        ?string $sourceSubcategory
    ): array {
        if ($sourceCategory !== null && trim($sourceCategory) !== '') {
            $query = PetzlCategoryMapping::query()
                ->where('source_category', trim($sourceCategory));

            if ($sourceSubcategory !== null && trim($sourceSubcategory) !== '') {
                $query->where('source_subcategory', trim($sourceSubcategory));
            }

            $mappingPath = $query
                ->whereNotNull('petzl_path')
                ->where('petzl_path', '!=', '')
                ->value('petzl_path');

            if ($mappingPath !== null) {
                return [trim($mappingPath)];
            }
        }

        return [
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
    }

    /**
     * Normalisiert Petzl-Produktnamen für die Slug-Erzeugung.
     *
     * Entfernt generische Zubehör-, Ersatzteil- und Versionszusätze, damit
     * Produktvarianten möglichst auf die zugehörige Petzl-Produktfamilie
     * zurückgeführt werden können.
     *
     * @param string $name Der originale Produktname.
     *
     * @return string Der bereinigte Produktname.
     */
    private function normalizeProductName(string $productName): string
    {
        $name = $productName;

        /*
        |--------------------------------------------------------------------------
        | Versions- und EOL-Zusätze
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+before\s+\d{4}$/i', '', $name);

        /*
        |--------------------------------------------------------------------------
        | Generische Zubehör-/Ersatzteil-Zusätze
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+(Pouch|Headband|Rope|Sleeve|Stays)$/i', '', $name);
        $name = preg_replace('/\s+(Work Seat|Charging Base|Mounting Plate|Extension Cord)$/i', '', $name);
        $name = preg_replace('/\s+(Cover Kit|Locking Accessory)$/i', '', $name);

        /*
        |--------------------------------------------------------------------------
        | Prefixe für Ersatzteile
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/^(Spare|Replacement)\s+/i', '', $name);
        $name = preg_replace('/^(Elastic Band|Foot Loop|Strap|Bag|Covering|Screw|Screws|Bars|Pin Screw)\s+for\s+/i', '', $name);

        /*
        |--------------------------------------------------------------------------
        | Nachgestellte Zielprodukt-Zusätze
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+for\s+.+$/i', '', $name);

        /*
        |--------------------------------------------------------------------------
        | Produktnummern innerhalb des Namens entfernen, z. B. "ABSORBICA® L010 Pouch"
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+[A-Z]\d{3,}[A-Z0-9]*\s+/i', ' ', $name);

        /*
        |--------------------------------------------------------------------------
        | Generische Zubehör-Endungen entfernen
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+(Pouch|Headband|Sleeve|Stays|Rope)$/i', '', $name);
        $name = preg_replace('/\s+(Charging Base|Mounting Plate|Extension Cord|Work Seat)$/i', '', $name);

        return trim($name);
    }

    /**
     * Erzeugt mögliche Slug-Kandidaten für die Petzl-Produktseite.
     *
     * Die Reihenfolge ist bewusst gewählt:
     * - Zunächst allgemeine Slug-Transformationen.
     * - Danach Sprachvarianten.
     * - Anschließend bekannte Produktfamilien.
     * - Zum Schluss spezielle deutsche Petzl-Marketing-Slugs.
     *
     * @param string $slug Ausgangs-Slug des Produkts.
     * @return \Illuminate\Support\Collection<int, string> Liste eindeutiger Slug-Kandidaten.
     */
    protected function buildSlugCandidates(string $slug): \Illuminate\Support\Collection
    {
        $candidates = [
            /*
            |--------------------------------------------------------------------------
            | Original slug
            |--------------------------------------------------------------------------
            */
            $slug,

            /*
            |--------------------------------------------------------------------------
            | Generic slug transformations
            |--------------------------------------------------------------------------
            */
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

            /*
            |--------------------------------------------------------------------------
            | Language variants
            |--------------------------------------------------------------------------
            */
            str_replace('-EUROPEAN-VERSION', '-EUROPÄISCHE-AUSFÜHRUNG', $slug),
            str_replace('-INTERNATIONAL-VERSION', '-INTERNATIONALE-AUSFÜHRUNG', $slug),
        ];

        /*
        |--------------------------------------------------------------------------
        | Product family aliases
        |--------------------------------------------------------------------------
        */
        if (str_contains($slug, 'AIRLINE')) {
            $candidates[] = 'AIRLINE';
        }

        if (str_contains($slug, 'AM-D')) {
            $candidates[] = 'AMD';
            $candidates[] = 'Am-D';
        }

        if (str_contains($slug, 'ARIA')) {
            $candidates[] = 'ARIA';
        }

        if (str_contains($slug, 'ASAP-LOCK')) {
            $candidates[] = 'ASAP-LOCK';
        }

        if (str_contains($slug, 'ASAP')) {
            $candidates[] = 'ASAP';
        }

        if (str_contains($slug, 'ASTRO')) {
            $candidates[] = 'ASTRO';
        }

        if (str_contains($slug, 'AVAO')) {
            $candidates[] = 'AVAO';
            $candidates[] = 'AVAO-FAST';
        }

        if (str_contains($slug, 'AXIS-11-MM')) {
            $candidates[] = 'AXIS-11-MM';
        }

        if (str_contains($slug, 'BM-D')) {
            $candidates[] = 'BMD';
            $candidates[] = 'Bm-D';
        }

        if (str_contains($slug, 'EJECT')) {
            $candidates[] = 'EJECT';
        }

        if (str_contains($slug, 'GRILLON')) {
            $candidates[] = 'GRILLON';
        }

        if (str_starts_with($slug, 'I-D')) {
            $candidates[] = str_replace('I-D', 'ID', $slug);
        }

        if ($slug === 'JAG-SYSTEM') {
            $candidates[] = 'JAG-SYSTEM';
            $candidates[] = 'JAG';
        }

        if ($slug === 'JAG-TRAXION') {
            $candidates[] = 'JAG-TRAXION';
            $candidates[] = 'JAG';
        }

        if (str_contains($slug, 'KNEE-ASCENT')) {
            $candidates[] = 'KNEE-ASCENT-KIT';
            $candidates[] = 'KNEE-ASCENT';
        }

        if (str_contains($slug, 'NAJA')) {
            $candidates[] = 'NAJA';
        }

        if (str_contains($slug, 'NEST')) {
            $candidates[] = 'NEST';
        }

        if (str_contains($slug, 'NEWTON')) {
            $candidates[] = 'NEWTON';
            $candidates[] = 'NEWTON-FAST';
            $candidates[] = 'NEWTON-EASYFIT';
        }

        if (str_contains($slug, 'PANTIN')) {
            $candidates[] = 'PANTIN';
            $candidates[] = 'PANTIN-CLICK';
        }

        if (str_contains($slug, 'PIXA')) {
            $candidates[] = 'PIXA';
            $candidates[] = 'PIXA-3R';
        }

        if (str_contains($slug, 'SEQUOIA')) {
            $candidates[] = 'SEQUOIA';
            $candidates[] = 'SEQUOIA-SRT';
        }

        if (str_contains($slug, 'STRATO')) {
            $candidates[] = 'STRATO';
            $candidates[] = 'STRATO-VENT';
        }

        if (str_contains($slug, 'VERTEX')) {
            $candidates[] = 'VERTEX';
            $candidates[] = 'VERTEX-VENT';
        }

        if (str_contains($slug, 'VOLT')) {
            $candidates[] = 'VOLT';
            $candidates[] = 'VOLT-WIND';
            $candidates[] = 'VOLT-LIGHT';
        }

        /*
        |--------------------------------------------------------------------------
        | German Petzl marketing slugs
        |--------------------------------------------------------------------------
        */
        if (str_contains($slug, 'PROGRESS-ADJUST-I')) {
            $candidates[] = 'PROGRESS-ADJUST-I-Verbindungsmittel-zur-Fortbewegung';
            $candidates[] = 'PROGRESS-ADJUST-I-Verbindungsmittel-zur-Positionierung';
        }

        if (str_contains($slug, 'TOOLINK')) {
            $candidates[] = 'TOOLINK-S-und-TOOLTAPE';
        }

        return collect($candidates)
            ->filter()
            ->unique()
            ->values();
    }
}
