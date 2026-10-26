<?php

namespace App\Services\ShopComparison;

use App\Models\ProductImage;
use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;

/**
 * Bild-Links aus einer Shop-Momentaufnahme in die Datenbank übernehmen.
 *
 * Nur für zugeordnete Produkte, die in der Datenbank noch kein Bild haben –
 * z. B. ausgelaufene Artikel, die früher im Shop waren und für die es beim
 * Hersteller keine Bilder mehr gibt. Der Shop selbst wird dabei nicht gelesen
 * oder geändert, es werden nur die gespeicherten Daten der Momentaufnahme genutzt.
 */
class WooSnapshotImages
{
    /** @var array<string, int> */
    public array $counts = ['Produkte mit Shop-Bildern' => 0, 'schon mit Bild' => 0, 'Bilder' => 0];

    /** @var list<string> */
    public array $lines = [];

    public function run(WooSnapshot $snapshot, bool $dryRun): void
    {
        $byProduct = [];

        // Hauptprodukte zuerst, damit ihr Titelbild vor den Variantenbildern steht
        $items = WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->whereNotNull('matched_product_id')
            ->orderByRaw('CASE WHEN woo_parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get(['id', 'woo_parent_id', 'name', 'matched_product_id', 'payload']);

        foreach ($items as $item) {
            foreach ($this->imageUrls((array) $item->payload) as $url) {
                $byProduct[$item->matched_product_id]['urls'][$url] = true;
            }

            $byProduct[$item->matched_product_id]['name'] ??= (string) $item->name;
        }

        $withImages = ProductImage::query()->whereIn('product_id', array_keys($byProduct))->distinct()->pluck('product_id')->flip();

        foreach ($byProduct as $productId => $data) {
            $urls = array_keys($data['urls'] ?? []);

            if ($urls === []) {
                continue;
            }

            if (isset($withImages[$productId])) {
                $this->counts['schon mit Bild']++;

                continue;
            }

            $this->counts['Produkte mit Shop-Bildern']++;
            $this->counts['Bilder'] += count($urls);
            $this->lines[] = "{$productId} {$data['name']}: ".count($urls).' Bild(er)';

            if ($dryRun) {
                continue;
            }

            foreach ($urls as $index => $url) {
                ProductImage::query()->firstOrCreate(
                    ['product_id' => $productId, 'url' => $url],
                    ['is_main' => $index === 0],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function imageUrls(array $payload): array
    {
        $images = $payload['images'] ?? [];

        if (isset($payload['image']) && is_array($payload['image'])) {
            $images[] = $payload['image'];
        }

        $urls = [];

        foreach ((array) $images as $image) {
            $src = is_array($image) ? trim((string) ($image['src'] ?? '')) : '';

            if ($src === '' || ! preg_match('#^https?://#i', $src) || strlen($src) > 255 || str_contains($src, 'woocommerce-placeholder')) {
                continue;
            }

            $urls[] = $src;
        }

        return array_values(array_unique($urls));
    }
}
