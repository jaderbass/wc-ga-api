<?php

namespace App\Services\Petzl;

use App\Models\Product;

/**
 * Importiert extrahierte Petzl-Beschreibungen in bestehende Produkte.
 */
class PetzlDescriptionImportService
{
    public function __construct(
        protected PetzlDescriptionFetcher $fetcher,
    ) {}

    /**
     * Ruft die Beschreibung von einer automatisch ermittelten Petzl-URL ab
     * und speichert sie am Produkt.
     *
     * @param Product $product Produkt, das aktualisiert werden soll.
     * @param string $url Petzl-Produkt-URL.
     *
     * @return array{url: string, description_html: string, hash: string}
     */
    public function importFromUrl(Product $product, string $url): array
    {
        $result = $this->fetcher->fetchFromUrl($url);

        $product->forceFill([
            'petzl_description_html' => $result['description_html'],
            'petzl_description_source_url' => $result['url'],
            'petzl_description_fetched_at' => now(),
            'petzl_description_hash' => $result['hash'],
            'description_source' => 'auto',
        ])->save();

        return $result;
    }

    /**
     * Ruft die Beschreibung von einer manuell hinterlegten Petzl-URL ab
     * und schützt die Zuordnung vor späteren automatischen Läufen.
     *
     * @param Product $product Produkt, das aktualisiert werden soll.
     * @param string $url Manuell gepflegte Petzl-Produkt-URL.
     *
     * @return array{url: string, description_html: string, hash: string}
     */
    public function importManuallyFromUrl(Product $product, string $url): array
    {
        $result = $this->fetcher->fetchFromUrl($url);

        $product->forceFill([
            'petzl_description_html' => $result['description_html'],
            'petzl_description_source_url' => $result['url'],
            'petzl_description_fetched_at' => now(),
            'petzl_description_hash' => $result['hash'],
            'description_source' => 'manual',
        ])->save();

        return $result;
    }

    /**
     * Prüft, ob für ein Produkt eine Petzl-Beschreibung automatisch importiert werden darf.
     */
    public function shouldImport(Product $product, bool $force = false): bool
    {
        if ($product->description_source === 'manual') {
            return false;
        }

        if ($force) {
            return true;
        }

        if (! empty($product->petzl_description_hash)) {
            return false;
        }

        return true;
    }
}
