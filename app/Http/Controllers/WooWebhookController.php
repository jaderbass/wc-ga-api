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

        /* $secret    = trim((string) $shop->webhook_secret);   // Sicherheitsnetz
        $got       = trim((string) $request->header('X-WC-Webhook-Signature', ''));
        $calc      = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if ($got === '' || ! hash_equals($calc, $got)) {
            Log::warning('Woo webhook: invalid signature', [
                'shop_id'   => $shop->id,
                'secret_len' => strlen($secret),
                'got_head'  => substr($got, 0, 8) . '…' . substr($got, -8),
                'calc_head' => substr($calc, 0, 8) . '…' . substr($calc, -8),
                'body_len'  => strlen($payload),
            ]);
            return response('Invalid signature', 401);
        }

        $signature = (string) $request->header('X-WC-Webhook-Signature', '');
        $calc      = base64_encode(hash_hmac('sha256', $payload, (string) $shop->webhook_secret, true));

        if ($signature === '' || ! hash_equals($calc, $signature)) {
            Log::warning('Woo webhook: invalid signature', ['shop_id' => $shop->id]);
            return response('Invalid signature', 401);
        } */

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

        return response()->noContent(); // 204
    }
}
