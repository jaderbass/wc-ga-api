<?php

namespace App\Services\Petzl;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Produktseite und Produktbilder auf petzl.com über die Artikelnummer finden.
 *
 * - Suche: https://www.petzl.com/de/de/search?q=<Artikelnummer>
 *   Die Trefferliste enthält auch allgemeine Empfehlungen – übernommen wird nur
 *   eine Produktseite, die eine unserer Artikelnummern enthält.
 * - Bilder: Petzl benennt Produktbilder nach Artikelnummer
 *   (".../media/product/E061AB01-TIKKA-focus-3_LowRes_….jpeg"); übernommen werden
 *   nur Bilder, deren Dateiname mit einer unserer Artikelnummern beginnt.
 */
class PetzlWebsiteMedia
{
    protected const BASE = 'https://www.petzl.com';

    protected const IMAGE = '#Sites-petzl-master-catalog/default/(dw[0-9a-f]+)/media/product/([A-Z0-9][^"\'\s?<>]*\.(?:jpe?g|png))#i';

    /**
     * @param  list<string>  $skus
     * @return array{url: string, html: string}|null
     */
    public function findProductPage(array $skus): ?array
    {
        $skus = array_values(array_filter(array_map(fn ($sku) => strtoupper(trim((string) $sku)), $skus)));

        // Eine Suche je Stammnummer, neuere zuerst: Bei Produkten mit alter und neuer
        // Version (C069AA… / C069AB…) findet petzl.com nur die aktuelle Nummer.
        $search = [];

        foreach ($skus as $sku) {
            $search[substr(str_replace(' ', '', $sku), 0, 6)] ??= $sku;
        }

        krsort($search);

        foreach (array_slice(array_values($search), 0, 4) as $sku) {
            foreach ($this->candidates($sku) as $url) {
                $html = $this->get($url);

                if ($html !== null && $this->containsAny($html, $skus)) {
                    return ['url' => $url, 'html' => $html];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $skus
     * @return list<string> Bild-URLs, Reihenfolge wie auf der Seite, ohne Doppelte
     */
    public function imageUrls(string $html, array $skus): array
    {
        $urls = [];

        // Petzl benennt Bilder oft nur nach der ersten Größe (C073BA00) oder der
        // Stammnummer (C072GA) – daher auch die ersten 6 Zeichen unserer Nummer zulassen.
        // Ältere Nummern mit Leerzeichen ("M33A SL") stehen im Dateinamen mit
        // Bindestrich ("M33A-SL-OK-SL"), oft auch nur der erste Teil ("L22A-ZILLON").
        $prefixes = [];

        foreach ($skus as $sku) {
            $sku = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $sku)));
            $compact = str_replace(' ', '', $sku);

            if ($compact === '') {
                continue;
            }

            $prefixes[] = $compact;

            if (str_contains($sku, ' ')) {
                $prefixes[] = str_replace(' ', '-', $sku);
                $first = strstr($sku, ' ', true);

                if (strlen($first) >= 3) {
                    $prefixes[] = $first;
                }
            } elseif (preg_match('/^[A-Z]\d{3}[A-Z]{2}\d{2}/', $compact)) {
                $prefixes[] = substr($compact, 0, 6);
            }
        }

        preg_match_all(self::IMAGE, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $version, $file]) {
            $name = strtoupper($file);

            foreach (array_unique($prefixes) as $prefix) {
                if (preg_match('/^'.preg_quote($prefix, '/').'(?:\d{2})?[-_]/', $name)) {
                    $urls[$file] ??= self::BASE.'/on/demandware.static/-/Sites-petzl-master-catalog/default/'.$version.'/media/product/'.$file;
                    break;
                }
            }
        }

        return array_values($urls);
    }

    /**
     * @return list<string>
     */
    protected function candidates(string $sku): array
    {
        $html = $this->get(self::BASE.'/de/de/search?q='.rawurlencode($sku));

        if ($html === null) {
            return [];
        }

        preg_match_all('#href="(/de/de/(?:sport|professional)/produkte/[^"]+_(?:SPO|PRO)_[A-Za-z0-9]+\.html)"#', $html, $matches);

        return array_values(array_unique(array_map(fn ($path) => self::BASE.$path, array_slice($matches[1], 0, 8))));
    }

    /**
     * @param  list<string>  $skus
     */
    protected function containsAny(string $html, array $skus): bool
    {
        foreach ($skus as $sku) {
            if ($sku !== '' && str_contains($html, $sku)) {
                return true;
            }
        }

        return false;
    }

    protected function get(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (PS Alpin Produktdatenbank)'])->timeout(20)->get($url);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }
}
