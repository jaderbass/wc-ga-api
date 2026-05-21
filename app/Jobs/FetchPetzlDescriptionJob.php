<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Petzl\PetzlDescriptionImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lädt eine Petzl-Beschreibung und speichert sie am Produkt.
 */
class FetchPetzlDescriptionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Anzahl Wiederholungsversuche.
     */
    public int $tries = 3;

    /**
     * Sekunden bis zum nächsten Versuch.
     */
    public int $backoff = 60;

    /**
     * @param int $productId ID des Produkts.
     * @param string $url URL der Petzl-Produktseite.
     */
    public function __construct(
        public int $productId,
        public string $url,
    ) {}

    /**
     * Führt den Import der Petzl-Beschreibung aus.
     */
    public function handle(
        PetzlDescriptionImportService $importService,
    ): void {
        $product = Product::findOrFail($this->productId);

        $importService->importFromUrl(
            $product,
            $this->url
        );
    }
}
