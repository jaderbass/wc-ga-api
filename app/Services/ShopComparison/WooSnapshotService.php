<?php

namespace App\Services\ShopComparison;

use App\Models\Shop;
use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use App\Services\Woo\WooClient;
use Closure;
use Generator;
use RuntimeException;
use Throwable;

/**
 * Liest alle Produkte und Varianten aus dem WooCommerce-Shop und speichert sie als Momentaufnahme.
 * Es werden ausschließlich GET-Anfragen gestellt, in den Shop wird nichts geschrieben.
 */
final class WooSnapshotService
{
    private const PER_PAGE = 100;

    private const MAX_PAGES = 500;

    /** Meta-Felder, in denen Plugins (Germanized u. a.) die GTIN/EAN ablegen. */
    private const EAN_META_KEYS = [
        '_global_unique_id', '_ts_gtin', '_gtin', 'gtin', '_ean', 'ean', '_wpm_gtin_code', 'hwp_product_gtin', 'hwp_var_gtin',
    ];

    /**
     * @param  (Closure(string, array<string,mixed>): array<int|string,mixed>)|null  $fetch  Nur für Tests; Standard ist WooClient::get().
     */
    public function __construct(
        private ?Closure $fetch = null,
        private int $pauseMs = 0,
    ) {}

    /**
     * @param  (Closure(int, int): void)|null  $progress  erhält (Produkte, Varianten)
     */
    public function run(Shop $shop, ?Closure $progress = null): WooSnapshot
    {
        $fetch = $this->fetch ?? $this->readOnlyFetcher($shop);

        $snapshot = WooSnapshot::create([
            'shop_id' => $shop->id,
            'status' => WooSnapshot::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $products = 0;
        $variations = 0;

        try {
            foreach ($this->pages($fetch, 'products', ['status' => 'any', 'orderby' => 'id', 'order' => 'asc']) as $product) {
                $this->store($snapshot, $product, null);
                $products++;

                if (($product['type'] ?? null) === WooSnapshotItem::TYPE_VARIABLE) {
                    $endpoint = 'products/'.(int) $product['id'].'/variations';

                    foreach ($this->pages($fetch, $endpoint, ['orderby' => 'id', 'order' => 'asc']) as $variation) {
                        $this->store($snapshot, $variation, $product);
                        $variations++;
                    }
                }

                if ($progress) {
                    $progress($products, $variations);
                }
            }

            $snapshot->update([
                'status' => WooSnapshot::STATUS_COMPLETED,
                'products_count' => $products,
                'variations_count' => $variations,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $snapshot->update([
                'status' => WooSnapshot::STATUS_FAILED,
                'products_count' => $products,
                'variations_count' => $variations,
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ]);

            throw $e;
        }

        return $snapshot->refresh();
    }

    private function readOnlyFetcher(Shop $shop): Closure
    {
        $client = new WooClient($shop);

        return fn (string $endpoint, array $query): array => $client->get($endpoint, $query);
    }

    /**
     * @return Generator<int, array<string,mixed>>
     */
    private function pages(Closure $fetch, string $endpoint, array $query): Generator
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($page > 1 && $this->pauseMs > 0) {
                usleep($this->pauseMs * 1000);
            }

            $rows = $fetch($endpoint, $query + ['per_page' => self::PER_PAGE, 'page' => $page]);

            if (! array_is_list($rows)) {
                throw new RuntimeException("Unerwartete Antwort der Shop-API für {$endpoint} (Seite {$page}).");
            }

            foreach ($rows as $row) {
                if (is_array($row) && isset($row['id'])) {
                    yield $row;
                }
            }

            if (count($rows) < self::PER_PAGE) {
                return;
            }
        }

        throw new RuntimeException('Abbruch: mehr als '.self::MAX_PAGES." Seiten für {$endpoint}.");
    }

    private function store(WooSnapshot $snapshot, array $item, ?array $parent): void
    {
        $sku = $this->text($item['sku'] ?? null, 191);
        $ean = $this->ean($item);

        WooSnapshotItem::create([
            'woo_snapshot_id' => $snapshot->id,
            'woo_id' => (int) $item['id'],
            'woo_parent_id' => $parent ? (int) $parent['id'] : null,
            'type' => $parent ? WooSnapshotItem::TYPE_VARIATION : ($this->text($item['type'] ?? null, 20) ?? 'simple'),
            'status' => $this->text($item['status'] ?? null, 20),
            'sku' => $sku,
            'sku_key' => ComparisonKey::sku($sku),
            'ean' => $this->text($ean, 64),
            'ean_key' => ComparisonKey::ean($ean),
            'name' => $this->text($this->name($item, $parent), 500),
            'regular_price' => $this->text($item['regular_price'] ?? null, 32),
            'payload' => $item,
        ]);
    }

    private function ean(array $item): ?string
    {
        $direct = $this->text($item['global_unique_id'] ?? null);

        if ($direct !== null) {
            return $direct;
        }

        foreach ((array) ($item['meta_data'] ?? []) as $meta) {
            $key = strtolower((string) ($meta['key'] ?? ''));
            $value = $meta['value'] ?? null;

            if (in_array($key, self::EAN_META_KEYS, true) && is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function name(array $item, ?array $parent): ?string
    {
        if (! $parent) {
            return $this->text($item['name'] ?? null);
        }

        $options = array_filter(array_map(
            fn ($attribute) => is_array($attribute) ? trim((string) ($attribute['option'] ?? '')) : '',
            (array) ($item['attributes'] ?? [])
        ));

        $base = (string) ($parent['name'] ?? '');

        return $options ? $base.' – '.implode(' / ', $options) : $base;
    }

    private function text(mixed $value, ?int $max = null): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '') {
            return null;
        }

        return $max ? mb_substr($value, 0, $max) : $value;
    }
}
