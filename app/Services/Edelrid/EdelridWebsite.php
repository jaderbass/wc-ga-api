<?php

namespace App\Services\Edelrid;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Produktdaten von edelrid.com lesen.
 *
 * - Produktseiten: aus der Sitemap https://edelrid.com/sitemap.edelrid.com.products.xml
 *   (deutsche Seiten, Profi-Bereich; optional auch Sport)
 * - Je Seite (serverseitig gerendert):
 *   - Varianten mit Artikelnummer, EAN (gtin) und Gewicht (data-…-variants-value)
 *   - Kurztext unter dem Produktnamen, Abschnitte "Beschreibung", "Features",
 *     "Technische Informationen"
 *   - PDF-Downloads (Gebrauchsanleitung, Konformitätserklärung …)
 *   - Galerie: Produktbilder (ed-image-product) vor Anwendungsbildern (ed-image-action),
 *     Bildserver shopapi.edelrid.com, Größe web-l (1920 px, WebP)
 */
class EdelridWebsite
{
    public const SITEMAP = 'https://edelrid.com/sitemap.edelrid.com.products.xml';

    public const IMAGE = 'https://shopapi.edelrid.com/v1/media/image/edelrid/%s/web-l';

    /**
     * @return list<string>
     */
    public function productUrls(bool $includeSport = false): array
    {
        $xml = $this->get(self::SITEMAP) ?? '';
        preg_match_all('#<loc>(https://edelrid\.com/de-de/(professional|sport)/[^<]+)</loc>#', $xml, $matches, PREG_SET_ORDER);

        $urls = [];

        foreach ($matches as [, $url, $area]) {
            if ($area === 'professional' || $includeSport) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * @return array{url: string, variants: list<array{sku: string, gtin: ?string, weight: ?int, size: string}>, teaser: ?string, description: ?string, features: ?string, technical: ?string, pdfs: array<string, string>, images: list<string>}|null
     */
    public function page(string $url): ?array
    {
        $html = $this->get($url);

        return $html === null ? null : self::parse($html, $url);
    }

    /**
     * @return array{url: string, variants: list<array{sku: string, gtin: ?string, weight: ?int, size: string}>, teaser: ?string, description: ?string, features: ?string, technical: ?string, pdfs: array<string, string>, images: list<string>}
     */
    public static function parse(string $html, string $url = ''): array
    {
        return [
            'url' => $url,
            'variants' => self::variants($html),
            'teaser' => self::teaser($html),
            'description' => self::section($html, 'beschreibung'),
            'features' => self::section($html, 'features'),
            'technical' => self::section($html, 'technische-informationen'),
            'pdfs' => self::pdfs($html),
            'images' => self::images($html),
        ];
    }

    /**
     * @return list<array{sku: string, gtin: ?string, weight: ?int, size: string}>
     */
    public static function variants(string $html): array
    {
        if (! preg_match('/data-product-detail-description-variants-value="([^"]+)"/', $html, $m)) {
            return [];
        }

        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        $variants = [];

        $walk = function ($node) use (&$walk, &$variants): void {
            if (! is_array($node)) {
                return;
            }

            if (isset($node['articleNumber'])) {
                $sku = preg_replace('/\D+/', '', (string) $node['articleNumber']);
                $variants[$sku] = [
                    'sku' => $sku,
                    'gtin' => preg_match('/^\d{8,14}$/', (string) ($node['gtin'] ?? '')) ? (string) $node['gtin'] : null,
                    'weight' => is_numeric($node['weight'] ?? null) && (int) $node['weight'] > 0 ? (int) $node['weight'] : null,
                    'size' => (string) ($node['title'] ?? ''),
                ];

                return;
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };

        $walk($data ?? []);

        return array_values($variants);
    }

    public static function teaser(string $html): ?string
    {
        if (! preg_match('#<div class="ed-text-child-light-content">(.*?)</div>#s', $html, $m)) {
            return null;
        }

        $text = self::text($m[1]);

        return $text !== '' ? $text : null;
    }

    /**
     * Inhalt eines Akkordeon-Abschnitts (id="beschreibung" …) als bereinigtes HTML.
     */
    public static function section(string $html, string $id): ?string
    {
        $pattern = '#<a id="'.preg_quote($id, '#').'" class="uk-accordion-title.*?<div class="uk-accordion-content[^"]*">(.*?)</div>\s*</li>#s';

        if (! preg_match($pattern, $html, $m)) {
            return null;
        }

        $clean = self::clean($m[1]);

        return self::text($clean) !== '' ? $clean : null;
    }

    /**
     * @return array<string, string> Bezeichnung => PDF-Link
     */
    public static function pdfs(string $html): array
    {
        preg_match_all('#<a class="[^"]*" href="(https://[^"]+\.pdf)"[^>]*download="([^"]*)"#', $html, $matches, PREG_SET_ORDER);

        $pdfs = [];

        foreach ($matches as [, $href, $label]) {
            $pdfs[html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8')] ??= $href;
        }

        return $pdfs;
    }

    /**
     * Bild-Links: zuerst Produktbilder, dann Anwendungsbilder, ohne Doppelte.
     *
     * @return list<string>
     */
    public static function images(string $html): array
    {
        preg_match_all('#<li\s+class="ed-product-detail-banner-image\s+([^"]*)"[^>]*>(.*?)</li>#s', $html, $items, PREG_SET_ORDER);

        $product = [];
        $action = [];

        foreach ($items as [, $class, $body]) {
            if (! preg_match('#/media/image/edelrid/(\d+)/web-#', $body, $m)) {
                continue;
            }

            if (str_contains($class, 'ed-image-product')) {
                $product[$m[1]] = true;
            } else {
                $action[$m[1]] = true;
            }
        }

        return array_map(fn ($id) => sprintf(self::IMAGE, $id), array_keys($product + $action));
    }

    /**
     * Erster Satz eines Textes (für die Kurzbeschreibung, falls kein Kurztext da ist).
     */
    public static function firstSentence(string $html): ?string
    {
        $text = self::text($html);

        if ($text === '') {
            return null;
        }

        return preg_match('/^(.{20,}?[.!?])(\s|$)/u', $text, $m) ? $m[1] : mb_substr($text, 0, 250);
    }

    /**
     * Nur einfache Auszeichnung behalten (Absätze, Listen, Tabellen, fett/kursiv).
     */
    protected static function clean(string $html): string
    {
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><table><thead><tbody><tr><th><td><h4><h5>');
        $html = (string) preg_replace('/\s(class|style|id|data-[a-z-]+)="[^"]*"/i', '', $html);
        $html = (string) preg_replace('/[ \t]*\n\s*/', "\n", $html);

        return trim(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    protected function get(string $url): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (PS Alpin Produktdatenbank)'])->timeout(30)->get($url);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }
}
