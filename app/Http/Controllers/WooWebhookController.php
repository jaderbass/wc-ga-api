<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WooWebhookController extends Controller
{
    /* public function __invoke(Request $request, int $shopId)
    {
        $shop = Shop::findOrFail($shopId);

        $signature = $request->header('X-WC-Webhook-Signature');
        $calc = base64_encode(hash_hmac('sha256', $request->getContent(), $shop->webhook_secret, true));
        if ($signature !== $calc) {
            Log::warning('Woo webhook invalid signature', ['shopId' => $shopId]);
            return response('Invalid signature', 401);
        }

        $topic = $request->header('X-WC-Webhook-Topic');
        $payload = $request->json()->all();

        Log::info('Woo webhook received', ['topic' => $topic, 'id' => $payload['id'] ?? null]);

        return response('OK', 200);
    } */

    public function __invoke(Request $request, int $shop)
    {
        return response()->json([
            'ok'    => true,
            'shop'  => $shop,
            'topic' => $request->header('X-WC-Webhook-Topic'),
        ]);
    }
}
