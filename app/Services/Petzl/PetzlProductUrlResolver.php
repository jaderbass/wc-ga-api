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

        $originalSlug = str($productName)
            ->upper()
            ->replaceMatches('/[^A-Z0-9ÄÖÜ]+/u', '-')
            ->trim('-')
            ->toString();

        $normalizedProductName = $this->normalizeProductName($productName);

        $slug = str($normalizedProductName)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->toString();

        $paths = $this->resolvePaths($sourceCategory, $sourceSubcategory);

        $slugCandidates = $this->buildSlugCandidates($slug, $originalSlug);

        Log::info('Petzl slug candidates.', [
            'slug' => $slug,
            'original_slug' => $originalSlug,
            'slug_candidates' => $slugCandidates->all(),
        ]);

        $markets = [
            'Professional',
            'Sport',
        ];

        $candidateUrls = collect();

        foreach ($markets as $market) {
            foreach ($paths as $path) {
                foreach ($slugCandidates as $slugCandidate) {
                    $candidateUrls->push(
                        "https://www.petzl.com/DE/de/{$market}/{$path}/{$slugCandidate}"
                    );
                }
            }
        }

        $candidateUrls = $candidateUrls->all();

        Log::info('Petzl resolver candidate URLs.', [
            'product_name' => $productName,
            'slug' => $slug,
            'candidate_urls' => $candidateUrls,
        ]);

        Log::debug('Petzl resolver stats.', [
            'product' => $productName,
            'candidate_count' => count($candidateUrls),
        ]);

        foreach ($candidateUrls as $url) {
            $start = microtime(true);

            $response = Http::withHeaders([
                'User-Agent' => 'GeoAlpin Product Importer',
            ])
                ->connectTimeout(3)
                ->timeout(8)
                ->get($url);

            Log::debug('Petzl request finished.', [
                'url' => $url,
                'status' => $response->status(),
                'time' => round(microtime(true) - $start, 3),
            ]);

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
            'Ersatzteile',
            'Abseilgerate',
            'Stirnlampen',
            'Ersatzteile-fur-Beleuchtung',
            'Anschlageinrichtungen',
            'Karabiner-und-expressets',
            'Verbindungsmittel-und-Falldampfer',
            'Seilklemmen',
            'Seilklemmen-fuer-den-Aufstieg-am-Seil',
            'Kits',
            'Helme',
            'Gurte',
            'Auffang--und-Haltegurte',
            'Gurte-zum-Arbeiten-am-Seil',
            'Karabiner-und-Verbindungselemente',
            'Seile',
            'Abseilgeraete',
            'Rollen',
            'Rettung',
            'Transporttaschen',
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
     * @param string $productName Der originale Produktname.
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
        | Zielprodukt aus Zubehörbezeichnungen extrahieren
        |--------------------------------------------------------------------------
        |
        | Beispiele:
        | - Bag for NEST litter                → NEST litter
        | - Covering for PODIUM Work Seat      → PODIUM Work Seat
        | - Strap for EJECT                    → EJECT
        | - Pouch for ASAP'SORBER              → ASAP'SORBER
        | - Kit for FAST TL Buckle Cover 28 mm → FAST TL Buckle Cover 28 mm
        |
        */
        $name = preg_replace(
            '/^(?:Bag|Bars?|Charging Cable|Covering|Elastic(?: Band)?|Foot Loop|Headband|Kit|Pin Screw|Pouch|Retrieval Ball|Screws?|Straps?)\s+for\s+/i',
            '',
            $name,
        );

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
        $name = preg_replace('/\b[A-Z]\d{3,}[A-Z0-9]*\b/i', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        /*
        |--------------------------------------------------------------------------
        | Generische Zubehör-Endungen entfernen
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/\s+(Pouch|Headband|Sleeve|Stays|Rope)$/i', '', $name);
        $name = preg_replace('/\s+(Charging Base|Mounting Plate|Extension Cord|Work Seat)$/i', '', $name);

        /*
        |--------------------------------------------------------------------------
        | Vorangestellte Stirnband-Bezeichnungen entfernen
        |--------------------------------------------------------------------------
        */
        $name = preg_replace('/^(?:Spare\s+)?Headband\s+/i', '', $name);

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
    protected function buildSlugCandidates(
        string $slug,
        ?string $originalSlug = null
    ): \Illuminate\Support\Collection {
        $slugUpper = strtoupper($slug);

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
            str_replace('-CHARGER', '-Ladegerät', $slug),
        ];

        /*
        |--------------------------------------------------------------------------
        | German accessory slugs
        |--------------------------------------------------------------------------
        |
        | Einige Petzl-Ersatzteile verwenden auf der deutschen Website eine
        | übersetzte Zubehörbezeichnung vor dem Namen des Zielprodukts.
        |
        | Beispiele:
        | - DUO Headband       → Kopfband-DUO
        | - DUO Mounting Plate → Trägerplatte-DUO
        |
        */
        if ($originalSlug !== null) {
            if (str_ends_with($originalSlug, '-HEADBAND')) {
                $productSlug = preg_replace('/-HEADBAND$/', '', $originalSlug);

                $candidates[] = 'Kopfband-' . $productSlug;
            }

            if (str_ends_with($originalSlug, '-MOUNTING-PLATE')) {
                $productSlug = preg_replace(
                    '/-MOUNTING-PLATE$/',
                    '',
                    $originalSlug
                );

                $candidates[] = 'Trägerplatte-' . $productSlug;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Generische deutsche Zubehör-Slugs
        |--------------------------------------------------------------------------
        */
        if ($originalSlug !== null) {
            /*
             * Headband ARIA → Kopfband-für-ARIA / Kopfband-ARIA
             */
            if (str_starts_with($originalSlug, 'HEADBAND-')) {
                $productSlug = preg_replace('/^HEADBAND-/', '', $originalSlug);

                $candidates[] = 'Kopfband-für-' . $productSlug;
                $candidates[] = 'Kopfband-' . $productSlug;
            }

            /*
             * SWIFT RL Rechargeable Battery → Akku-SWIFT-RL
             */
            if (str_ends_with($originalSlug, '-RECHARGEABLE-BATTERY')) {
                $productSlug = preg_replace(
                    '/-RECHARGEABLE-BATTERY$/',
                    '',
                    $originalSlug
                );

                $candidates[] = 'Akku-' . $productSlug;
            }

            /*
             * PIXA 3R Charging Base → Ladestation-PIXA-3R
             */
            if (str_ends_with($originalSlug, '-CHARGING-BASE')) {
                $productSlug = preg_replace(
                    '/-CHARGING-BASE$/',
                    '',
                    $originalSlug
                );

                $candidates[] = 'Ladestation-' . $productSlug;
            }

            /*
             * R1 Extension Cord → Verlängerungskabel-R1
             */
            if (str_ends_with($originalSlug, '-EXTENSION-CORD')) {
                $productSlug = preg_replace(
                    '/-EXTENSION-CORD$/',
                    '',
                    $originalSlug
                );

                $candidates[] = 'Verlängerungskabel-' . $productSlug;
            }

            /*
             * Charging Cable for CORE PRO → Ladekabel-für-CORE-PRO
             */
            if (str_starts_with($originalSlug, 'CHARGING-CABLE-FOR-')) {
                $productSlug = preg_replace(
                    '/^CHARGING-CABLE-FOR-/',
                    '',
                    $originalSlug
                );

                $candidates[] = 'Ladekabel-für-' . $productSlug;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Bekannte Petzl-Slug-Aliase
        |--------------------------------------------------------------------------
        |
        | Diese Bezeichnungen lassen sich nicht sinnvoll aus dem englischen Namen
        | ableiten oder verwenden redaktionelle Petzl-Sonderbezeichnungen.
        |
        */
        $knownSlugAliases = [
            'ABSORBENT-FOAM-BEFORE-2019' =>
            'Saugfähige-Schaumstoffpolster-vor-2019',

            'ABSORBICA-Y-FALL-ARREST-KIT' =>
            'ABSORBICA-Y-FALL-ARREST-KIT',

            'ASAP-SORBER-AXESS-POUCH' =>
            'Schutzhülle-ASAP-SORBER-AXESS',

            'ASAP-SORBER-L071-POUCH' =>
            'Schutzhülle-ASAP-SORBER-20-40',

            'AUXILIARY-CLOSED-BRAKE-FOR-I-D' =>
            'Zusätzliches-geschlossenes-Bremselement-für-I-D',

            'AUXILIARY-OPEN-BRAKE-FOR-I-D' =>
            'Zusätzliches-offenes-Bremselement-für-I-D',

            'BARS-FOR-FAST-45-MM-BUCKLES' =>
            'Stege-für-Schnallen-FAST-45-mm',

            'BOLT-STEEL' =>
            'COEUR-BOLT-STEEL',

            'BOLT-STAINLESS' =>
            'COEUR-BOLT-STAINLESS',

            'CHICANE-FRICTION-PINS' =>
            'Reibungselemente-CHICANE',

            'DOUBLEBACK-PLUS-LOCKING-ACCESSORY' =>
            'Zubehör-Verriegelung-DOUBLEBACK-PLUS',

            'DUFFEL-SHOULDER-STRAPS' =>
            'Schulterträger-DUFFEL',

            'E-LITE' =>
            'ePLUSLITE',

            'ELASTIC-KEEPERS-45-MM' =>
            'Elastische-Riemenhalter-45-mm',

            'EXO-AP-HOOK-ROPE' =>
            'Seil-EXO-AP-HOOK',

            'FALL-ARREST-AND-WORK-POSITIONING-KIT' =>
            'FALL-ARREST-AND-WORK-POSITIONING-KIT',

            'FAST-BUCKLE-45-MM-COVER-KIT' =>
            'Set-Abdeckungen-für-Schnalle-FAST-45-mm',

            'FAST-BUCKLE-COVER-KIT-28-MM' =>
            'Set-Abdeckungen-für-Schnalle-FAST-28-mm',

            'FIRSTAID-POUCH-FOR-FIRST-AID-KIT' =>
            'FIRSTAID-Erste-Hilfe-Tasche',

            'HI-VIZ-VEST-FOR-NEWTON-HARNESSES' =>
            'HI-VIZ-Weste-für-NEWTON-Gurte',

            'IDENTIFICATION-LABELS-FOR-PETZL-ROPES' =>
            'Kennzeichnungshülsen-für-Petzl-Seile',

            'JAG-SYSTEM-SLEEVE' =>
            'Hülle-JAG-SYSTEM',

            'JAG-RESCUE-KIT' =>
            'JAG-RESCUE-KIT',

            'KIT-FOR-FAST-TL-BUCKLE-COVER-28-MM' =>
            'Kit-Abdeckungen-für-Schnalle-FAST-TL-28-mm',

            'KIT-FOR-FAST-TL-BUCKLE-COVER-45-MM' =>
            'Set-Abdeckungen-für-Schnalle-FAST-TL-45-mm',

            'LANYARD-CONNECTOR-PARKING' =>
            'Verstausystem-für-das-Verbindungselement-des-Verbindungsmittels',

            'LARGE-D-SHAPED-GAP-FOR-ASTRO' =>
            'Großer-D-Ring-PCO-ASTRO',

            'LEG-LOOP-PADDING-FOR-NEWTON-HARNESS' =>
            'Beinschlaufenpolster-für-NEWTON-Gurte',

            'LEST-THE-PIPE' =>
            'Gewicht-THE-PIPE',

            'LEZARD-ADJUSTABLE-LANYARD' =>
            'Einstellbares-Verbindungsmittel-LEZARD',

            'MICROFLIP-REINFORCED-ROPE' =>
            'Stahlseil-MICROFLIP',

            'PIN-SCREW-FOR-ASTRO-GAP' =>
            'Schraube-Mittelstift-PCO-ASTRO',

            'PLASTIC-KEEPERS-45-MM' =>
            'Kunststoff-Riemenhalter-45-mm',

            'POUCH-FOR-ASAP-SORBER' =>
            'Schutzhülle-ASAP-SORBER-20-40',

            'QUICK-CHARGER' =>
            'Schnellladegerät',

            'QUICK-CHARGER-UK' =>
            'Schnellladegerät',

            'RECHARGEABLE-BATTERY-FOR-PIXA-3R' =>
            'Akku-für-PIXA-3R',

            'REFLECTIVE-STICKERS-BEFORE-2019' =>
            'Reflektierende-Aufkleber-vor-2019',

            'REPAIR-KIT-FOR-REPAIRABLE-RIG' =>
            'Reparaturset-RIG-reparierbare-Version',

            'ROPE-END-IDENTIFICATION-KIT' =>
            'Set-zur-Kennzeichnung-der-Seilenden-',

            'ROPE-PROTECTOR-CLIP' =>
            'Seilschutzklemme',

            'SCREW-FOR-I-D-AND-MICROGRAB' =>
            'Schrauben-I-D-und-MICROGRAB',

            'SCREWS-FOR-FAST-45-MM-BUCKLE' =>
            'Schrauben-für-Schnalle-FAST-45-mm',

            'SEWN-TERMINATION-SCREW' =>
            'Schraube-vernähte-Endverbindung',

            'SHACKLES' =>
            'Verbindungsbügel-für-den-PODIUM-Sitz',

            'SMALL-D-SHAPED-GAP-FOR-ASTRO' =>
            'Kleiner-D-Ring-PCO-ASTRO',

            'STRING-M' =>
            'STRING-M',

            'SWIFT-RL-RECHARGEABLE-BATTERY' =>
            'Akku-SWIFT-RL',

            'VOLT-WORK-SEAT' =>
            'Sitzbrett-für-VOLT-internationale-Ausführung',
        ];

        if (
            $originalSlug !== null
            && isset($knownSlugAliases[$originalSlug])
        ) {
            $candidates[] = $knownSlugAliases[$originalSlug];
        }

        /*
        |--------------------------------------------------------------------------
        | Product family aliases
        |--------------------------------------------------------------------------
        */
        if (str_contains($slugUpper, 'AIRLINE')) {
            $candidates[] = 'AIRLINE';
        }

        if (str_contains($slugUpper, 'AM-D')) {
            $candidates[] = 'AMD';
            $candidates[] = 'Am-D';
        }

        if (str_contains($slugUpper, 'ARIA')) {
            $candidates[] = 'ARIA';
        }

        if (str_contains($slugUpper, 'ASAPSORBER')) {
            $candidates[] = 'ASAP-SORBER';
        }

        if (str_contains($slugUpper, 'ASAP')) {
            $candidates[] = 'ASAP';
        }

        if (str_contains($slugUpper, 'ASAP-LOCK')) {
            $candidates[] = 'ASAP-LOCK';
        }

        if (str_contains($slugUpper, 'ASTRO')) {
            $candidates[] = 'ASTRO';
        }

        if (str_contains($slugUpper, 'AVAO')) {
            $candidates[] = 'AVAO';
            $candidates[] = 'AVAO-FAST';
        }

        if (str_contains($slugUpper, 'AXIS-11-MM')) {
            $candidates[] = 'AXIS-11-MM';
        }

        if (str_contains($slugUpper, 'BM-D')) {
            $candidates[] = 'BMD';
            $candidates[] = 'Bm-D';
        }

        if (str_contains($slugUpper, 'DUO')) {
            $candidates[] = 'DUO';
        }

        if (str_contains($slugUpper, 'EJECT')) {
            $candidates[] = 'EJECT';
        }

        if (str_contains($slugUpper, 'GRILLON')) {
            $candidates[] = 'GRILLON';
        }

        if (str_starts_with($slugUpper, 'I-D')) {
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

        if (str_contains($slugUpper, 'KNEE-ASCENT')) {
            $candidates[] = 'KNEE-ASCENT-KIT';
            $candidates[] = 'KNEE-ASCENT';
            $candidates[] = 'Set-KNEE-ASCENT';
        }

        if (str_contains($slugUpper, 'LITEPOD')) {
            $candidates[] = 'LITEPOD';
        }

        if (str_contains($slugUpper, 'NAJA')) {
            $candidates[] = 'NAJA';
        }

        if (str_contains($slugUpper, 'NEST')) {
            $candidates[] = 'NEST';
        }

        if (str_contains($slugUpper, 'NEWTON')) {
            $candidates[] = 'NEWTON';
            $candidates[] = 'NEWTON-FAST';
            $candidates[] = 'NEWTON-EASYFIT';
        }

        if (str_contains($slugUpper, 'PANTIN')) {
            $candidates[] = 'PANTIN';
            $candidates[] = 'PANTIN-CLICK';
        }

        if (str_contains($slugUpper, 'PIXA')) {
            $candidates[] = 'PIXA';
            $candidates[] = 'PIXA-3R';
        }

        if (str_contains($slugUpper, 'PODIUM')) {
            $candidates[] = 'PODIUM';
        }

        if (str_contains($slugUpper, 'SEQUOIA')) {
            $candidates[] = 'SEQUOIA';
            $candidates[] = 'SEQUOIA-SRT';
        }

        if (str_contains($slugUpper, 'STRATO')) {
            $candidates[] = 'STRATO';
            $candidates[] = 'STRATO-VENT';
        }

        if (str_contains($slugUpper, 'VERTEX')) {
            $candidates[] = 'VERTEX';
            $candidates[] = 'VERTEX-VENT';
        }

        if (str_contains($slugUpper, 'VOLT')) {
            $candidates[] = 'VOLT';
            $candidates[] = 'VOLT-WIND';
            $candidates[] = 'VOLT-LIGHT';
        }

        if (str_ends_with($slugUpper, '-CHARGER')) {
            $productSlug = preg_replace('/-charger$/i', '', $slug);
            $candidates[] = 'Ladegerät-für-' . strtoupper($productSlug);
        }

        /*
        |--------------------------------------------------------------------------
        | German Petzl marketing slugs
        |--------------------------------------------------------------------------
        */
        if (str_contains($slugUpper, 'PROGRESS-ADJUST-I')) {
            $candidates[] = 'PROGRESS-ADJUST-I-Verbindungsmittel-zur-Fortbewegung';
            $candidates[] = 'PROGRESS-ADJUST-I-Verbindungsmittel-zur-Positionierung';
        }

        if (str_contains($slugUpper, 'TOOLINK')) {
            $candidates[] = 'TOOLINK-S-und-TOOLTAPE';
        }

        return collect($candidates)
            ->filter()
            ->unique()
            ->values();
    }
}
