<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\Shop;
use App\Support\Woo\PayloadBuilder;
use App\Support\Woo\PayloadHasher;
use App\Support\Woo\SyncStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Kapselt den Outbound-Sync eines einzelnen Produkts zu WooCommerce.
 * - Payload via PayloadBuilder
 * - Optionaler Hash-Vergleich (onlyChanged)
 * - Upsert: zuerst Update (ID/SKU), sonst Create
 * - Pflegt last_sync_* Felder
 */
class WooProductService
{
    /**
     * @param \App\Support\Woo\PayloadBuilder $builder Erzeugt die Woo-Payload aus Product
     */
    public function __construct(
        protected PayloadBuilder $builder
    ) {}

    /**
     * Erstellt/aktualisiert ein Produkt in Woo.
     *
     * @param \App\Models\Product $product
     * @param \App\Models\Shop    $shop
     * @param bool $dryRun        Nur Preview/Hashes zurückgeben, keine API-Calls
     * @param bool $onlyChanged   Nur senden, wenn sich die Payload geändert hat
     * @return array{
     *   id?: int,
     *   status: string,
     *   action?: 'create'|'update'|'skip',
     *   skipped?: bool,
     *   changed?: bool,
     *   response?: array<string,mixed>|list<mixed>,
     *   payload?: array<string,mixed>,
     *   prevHash?: string,
     *   newHash?: string,
     *   message?: string
     * }
     */
    public function upsertProduct(Product $product, Shop $shop, bool $dryRun = false, bool $onlyChanged = false): array
    {
        $client  = new WooClient($shop);
        $payload = $this->builder->buildProductPayload($product);
        $hash    = PayloadHasher::make($payload);

        // Optional: nur senden, wenn sich die Payload geändert hat
        if ($onlyChanged && $product->payload_hash === $hash) {
            // Touch minimal, damit man den letzten Check-Zeitpunkt sieht
            $this->touchSyncMeta($product, SyncStatus::Synced->value, null, $hash);
            return [
                'status'  => SyncStatus::Synced->value,
                'skipped' => true,
                'id'      => $product->woo_product_id,
            ];
        }

        if ($dryRun) {
            return [
                'status'   => 'dry-run',
                'payload'  => $payload,
                'prevHash' => $product->payload_hash,
                'newHash'  => $hash,
            ];
        }

        try {
            $resp   = null;
            $action = null;

            // 1) Update per gespeicherter Woo-ID
            if (!empty($product->woo_product_id)) {
                $resp   = $client->put('products/' . $product->woo_product_id, $payload);
                $action = 'update:id';
            }

            // 2) Falls keine ID: per SKU suchen und updaten
            if ($resp === null && !empty($product->sku)) {
                if ($wooId = $this->findWooIdBySku($client, (string)$product->sku)) {
                    $product->woo_product_id = $wooId;
                    $product->save();
                    $resp   = $client->put('products/' . $wooId, $payload);
                    $action = 'update:sku';
                }
            }

            // 3) Wenn bisher nichts aktualisiert: neu anlegen
            if ($resp === null) {
                $resp   = $client->post('products', $payload);
                $action = 'create';
                if (isset($resp['id'])) {
                    $product->woo_product_id = (int) $resp['id'];
                }
            }

            // Erfolgs-Metadaten pflegen
            $this->touchSyncMeta($product, SyncStatus::Synced->value, null, $hash);

            return [
                'id'       => $product->woo_product_id,
                'status'   => SyncStatus::Synced->value,
                'action'   => $action,
                'response' => $resp ?? [],
            ];
        } catch (Throwable $e) {
            // Fehlerstatus pflegen
            $this->touchSyncMeta($product, 'error', $e->getMessage(), $hash);

            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }


    /**
     * Sucht in WooCommerce nach einem Produkt anhand der SKU und liefert die Woo-ID.
     *
     * Nutzt die REST-Route GET /products?sku=... (per_page=1).
     * Liefert null, wenn nichts gefunden oder der Request fehlschlägt (z. B. Connectivity).
     *
     * @param WooClient $client  Initialisierter Woo-Client für den Zielshop
     * @param string    $sku     Eindeutige Artikelnummer
     * @return int|null          Gefundene Produkt-ID in Woo oder null
     */
    protected function findWooIdBySku(WooClient $client, string $sku): ?int
    {
        try {
            $res = $client->get('products', ['sku' => $sku, 'per_page' => 1]);

            if (is_array($res) && !empty($res) && !empty($res[0]['id'])) {
                return (int) $res[0]['id'];
            }
        } catch (Throwable $e) {
            // Nur warnen; der Aufrufer kann anschließend Create versuchen
            Log::warning('Woo find-by-SKU failed', [
                'sku'   => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Pflegt die Synchronisations-Metafelder am lokalen Produkt (sofern vorhanden).
     *
     * Setzt last_synced_at, last_sync_status, last_sync_error und optional payload_hash.
     * Alle Zuweisungen erfolgen nur, wenn die jeweiligen Spalten in der Tabelle existieren.
     * Fehler beim Speichern werden geloggt, blockieren den Flow aber nicht.
     *
     * @param Product     $p            Das betroffene Produkt
     * @param string      $status       z. B. 'synced' oder 'error'
     * @param string|null $error        Fehlermeldung im Fehlerfall, sonst null
     * @param string|null $payloadHash  Neuer Payload-Hash (optional)
     * @return void
     */
    protected function touchSyncMeta(Product $p, string $status, ?string $error, ?string $payloadHash): void
    {
        try {
            if (Schema::hasColumn('products', 'last_synced_at')) {
                $p->last_synced_at = now();
            }
            if (Schema::hasColumn('products', 'last_sync_status')) {
                $p->last_sync_status = $status;
            }
            if (Schema::hasColumn('products', 'last_sync_error')) {
                $p->last_sync_error = $error;
            }
            if ($payloadHash !== null && Schema::hasColumn('products', 'payload_hash')) {
                $p->payload_hash = $payloadHash;
            }
            $p->save();
        } catch (Throwable $e) {
            Log::warning('touchSyncMeta failed', [
                'product_id' => $p->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
