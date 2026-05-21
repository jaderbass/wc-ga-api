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
     * Ruft die Beschreibung von einer Petzl-URL ab und speichert sie am Produkt.
     *
     * Manuell gepflegte Beschreibungen bleiben über description_source geschützt.
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
            'description_source' => $product->description_source === 'manual'
                ? 'manual'
                : 'auto',
        ])->save();

        return $result;
    }
}
