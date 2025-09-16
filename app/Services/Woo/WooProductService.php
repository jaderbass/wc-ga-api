<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Woo\PayloadBuilder;
use App\Support\Woo\PayloadHasher;
use App\Support\Woo\SyncStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Create/Update eines einzelnen Produkts in WooCommerce.
 * - Payload via Builder
 * - Optional onlyChanged über Hash
 * - Upsert-Logik: woo_product_id → PUT, sonst via SKU suchen → PUT, sonst POST
 * - last_sync_* Felder werden in jedem Pfad gepflegt
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

        // Unverändert? -> Skip (optional)
        if ($onlyChanged && $product->payload_hash === $hash) {
            Log::info('Skip upsert (unchanged payload)', ['productId' => $product->id]);
            // Touch minimal: Zeit/Status bleiben "synced"
            $product->last_synced_at    = now();
            $product->last_sync_status  = SyncStatus::Synced->value;
            $product->last_sync_error   = null;
            $product->save();

            return [
                'status'  => SyncStatus::Synced->value,
                'skipped' => true,
                'id'      => $product->woo_product_id,
            ];
        }

        if ($dryRun) {
            return [
                'status'  => 'dry-run',
                'payload' => $payload,
                'prevHash' => $product->payload_hash,
                'newHash' => $hash,
            ];
        }

        try {
            $resp = null;
            $action = null;

            // 1) Direkter Update-Pfad über gespeicherte Woo-ID
            if ($product->woo_product_id) {
                $resp = $client->put('products/' . $product->woo_product_id, $payload);
                $action = 'update:id';
            }

            // 2) Falls keine ID: per SKU suchen und updaten
            if (!$resp && !empty($product->sku)) {
                try {
                    $found = $client->get('products', ['sku' => $product->sku]);
                    if (is_array($found) && !empty($found[0]['id'])) {
                        $product->woo_product_id = (int) $found[0]['id'];
                        $product->save();
                        $resp = $client->put('products/' . $product->woo_product_id, $payload);
                        $action = 'update:sku';
                    }
                } catch (Throwable $e) {
                    // Nur warnen – wir versuchen danach einen Create
                    Log::warning('Woo find-by-SKU failed', [
                        'sku'   => $product->sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 3) Create, wenn bisher nichts aktualisiert wurde
            if (!$resp) {
                $resp = $client->post('products', $payload);
                $action = 'create';
                if (isset($resp['id'])) {
                    $product->woo_product_id = (int) $resp['id'];
                }
            }

            // Erfolgreich: Metafelder setzen
            $product->payload_hash     = $hash;
            $product->last_sync_status = SyncStatus::Synced->value;
            $product->last_synced_at   = now();
            $product->last_sync_error  = null;
            $product->save();

            Log::info('Woo upsert ok', [
                'productId' => $product->id,
                'wooId'     => $product->woo_product_id,
                'action'    => $action,
            ]);

            return [
                'id'       => $product->woo_product_id,
                'status'   => SyncStatus::Synced->value,
                'action'   => $action,
                'response' => $resp ?? [],
            ];
        } catch (Throwable $e) {
            // Fehlerstatus pflegen
            // robust: wenn Enum existiert und 'Failed'/'Error' Case vorhanden ist → dessen value, sonst 'error'
            $status = 'error';
            if (enum_exists(SyncStatus::class)) {
                foreach (SyncStatus::cases() as $case) {
                    if ($case->name === 'Failed' || $case->name === 'Error') {
                        $status = $case->value;
                        break;
                    }
                }
            }
            $product->last_sync_status = $status;

            $product->last_synced_at   = now();
            $product->last_sync_error  = $e->getMessage();
            $product->save();

            Log::error('Woo upsert failed', [
                'productId' => $product->id,
                'error'     => $e->getMessage(),
            ]);

            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
