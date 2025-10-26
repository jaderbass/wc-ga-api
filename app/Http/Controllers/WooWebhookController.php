<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WooWebhookController extends Controller
{
    /**
     * Verifiziert die Woo-Signatur und verarbeitet Ereignisse.
     */
    public function __invoke(Request $request, Shop $shop): Response
    {
        // WooWebhookController.php (ganz oben in __invoke)
        if (config('app.env') === 'local' && env('WOO_VERIFY_WEBHOOKS', true) === false) {
            Log::info('Woo webhook: bypass verify (local dev)');
            return response()->noContent();
        }


        $payload   = $request->getContent(); // RAW body!

        $topic   = (string) $request->header('X-WC-Webhook-Topic', '');
        $event   = (string) $request->header('X-WC-Webhook-Event', '');
        $body    = json_decode($payload, true) ?: [];

        // Optional: Topic-Whitelist
        $allowed = [
            'product.created',
            'product.updated',
            'product.deleted',
            // 'product_variation.created', 'product_variation.updated', 'product_variation.deleted',
        ];
        if ($topic && ! in_array($topic, $allowed, true)) {
            Log::info('Woo webhook: topic ignored', compact('topic', 'event'));
            return response('Ignored', 200);
        }

        // 👉 hier kurz & non-blocking arbeiten (Queue/Job o. Ä.)
        Log::info('Woo webhook: received', ['topic' => $topic, 'event' => $event, 'shop' => $shop->id]);

        // Spezieller Fall: Produkt wurde in Woo endgültig gelöscht → lokale woo_product_id nullen
        if ($topic === 'product.deleted') {
            $json = [];
            try {
                $json = $request->json()->all() ?: [];
            } catch (\Throwable $e) {
                // RAW-Body fallback (Woo variiert je nach Hook)
                $raw = (string) $request->getContent();
                try {
                    $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $ee) {
                }
            }
            $remoteId = data_get($json, 'id') ?? data_get($json, 'payload.id');
            if ($remoteId) {
                /** @var \App\Models\Product|null $p */
                $p = Product::query()->where('woo_product_id', (int) $remoteId)->first();
                if ($p) {
                    $p->woo_product_id = null;
                    $p->save();
                    Log::info('woo_id_cleared_on_remote_delete', [
                        'product_id' => $p->id,
                        'remote_id'  => (int) $remoteId,
                    ]);
                } else {
                    Log::info('woo_delete_webhook_no_local_match', [
                        'remote_id' => (int) $remoteId,
                    ]);
                }
            } else {
                Log::warning('woo_delete_webhook_no_id_in_payload');
            }
        }

        // Bei "product.updated" mit status=trash -> NICHT nullen (nur loggen)
        if ($topic === 'product.updated') {
            $json   = $request->json()->all() ?: [];
            $status = data_get($json, 'status') ?? data_get($json, 'payload.status');
            if ($status === 'trash') {
                Log::info('woo_trash_event_ignored_for_id_clear');
            }
        }

        return response()->noContent(); // 204
    }

    /**
     * Reagiert auf WooCommerce-Webhooks und löscht die lokale Woo-ID,
     * wenn ein Produkt **dauerhaft gelöscht** wird.
     *
     * @param Request $request
     * @param int|null $id (optional, falls Woo den ID-Teil mitsendet)
     * @return JsonResponse
     */
    public function handleWooDelete(Request $request, ?int $id = null): JsonResponse
    {
        $body = $request->getContent();
        $data = json_decode($body, true);

        // Nur bei permanentem Delete, nicht bei Papierkorb
        $event = $request->header('X-WC-Webhook-Topic');
        if (!str_contains($event, 'deleted')) {
            return response()->json(['ignored' => true]);
        }

        $wooId = $data['id'] ?? $id ?? null;
        if (!$wooId) {
            Log::warning('WooWebhook: delete received without ID');
            return response()->json(['status' => 'no_id'], 400);
        }

        $affected = \DB::table('products')
            ->where('woo_product_id', $wooId)
            ->update(['woo_product_id' => null]);

        Log::info('WooWebhook: product.deleted handled', [
            'woo_id' => $wooId,
            'affected' => $affected,
        ]);

        return response()->json(['cleared' => $affected]);
    }

}
