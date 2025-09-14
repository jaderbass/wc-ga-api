<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Woo\PayloadBuilder;
use App\Support\Woo\PayloadHasher;
use App\Support\Woo\SyncStatus;
use Illuminate\Support\Facades\Log;

/**
 * Class WooProductService
 *
 * Verantwortlich für Create/Update von Produkten in Woo.
 */
class WooProductService
{
    public function __construct(
        protected PayloadBuilder $builder
    ) {}

    /** @return array<string,mixed> */
    public function upsertProduct(Product $product, Shop $shop, bool $dryRun = false, bool $onlyChanged = false): array
    {
        $client  = new WooClient($shop);
        $payload = $this->builder->buildProductPayload($product);
        $hash    = PayloadHasher::make($payload);

        if ($onlyChanged && $product->payload_hash === $hash) {
            Log::info('Skip upsert (unchanged payload)', ['productId' => $product->id]);
            return ['status' => SyncStatus::Synced->value, 'skipped' => true, 'id' => $product->woo_product_id];
        }

        if ($dryRun) {
            return ['status' => 'dry-run', 'payload' => $payload];
        }

        if ($product->woo_product_id) {
            $resp = $client->put('products/' . $product->woo_product_id, $payload);
        } else {
            $resp = $client->post('products', $payload);
            if (isset($resp['id'])) {
                $product->woo_product_id = (int) $resp['id'];
            }
        }

        $product->payload_hash    = $hash;
        $product->last_sync_status = SyncStatus::Synced->value;
        $product->last_synced_at   = now();
        $product->last_sync_error  = null;
        $product->save();

        return ['id' => $product->woo_product_id, 'status' => SyncStatus::Synced->value, 'response' => $resp ?? []];
    }
}
