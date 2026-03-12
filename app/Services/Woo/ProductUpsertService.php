<?php

namespace App\Services\Woo;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Services\Woo\WooClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProductUpsertService
 *
 * Führt ein robustes Upsert (Create/Update) von WooCommerce-Hauptprodukten durch
 * und nutzt dabei einen SKU-Preflight (per WooProductLookupService), um
 * Duplicate-SKU-Fehler beim POST zu vermeiden.
 *
 * Wichtige Punkte:
 * - Für variable Produkte ist es Best Practice, **am Parent keine SKU** zu setzen.
 *   (Variante besitzt die SKU). Falls dennoch eine SKU im Payload übergeben wird,
 *   nutzt der Preflight diese zur Update-Erkennung.
 * - Preise werden in diesem Projekt bewusst NICHT synchronisiert.
 * - Nach erfolgreichem Create (POST) wird die Remote-ID in products.woo_product_id gespeichert.
 *
 * Konfiguration:
 * - Base URL, Version, Credentials: config('woo.api.*'), config('woo.default_api_version')
 *
 * Integration:
 * - Aus deinem bestehenden Produkt-Exporter/Service statt direktem POST/PUT:
 *     app(ProductUpsertService::class)->upsertProduct($product, $payload);
 *
 * Payload-Erwartung (Auszug, Woo-REST /products):
 * - Titel/Name, Beschreibung, Typ (simple|variable), Bilder, Attribute etc.
 * - SKU optional (für variable Eltern i. d. R. leer lassen)
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class ProductUpsertService
{
    protected PendingRequest $http;
    protected string $base;
    protected string $ver;

    public function __construct(
        protected WooProductLookupService $lookup
    ) {
        $this->base = rtrim((string) config('woo.api.base_url'), '/');
        $this->ver  = (string) config('woo.default_api_version', 'wc/v3');

        $this->http = Http::baseUrl($this->base . '/wp-json/' . $this->ver)
            ->withBasicAuth(
                (string) config('woo.api.key'),
                (string) config('woo.api.secret')
            )
            ->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4,
                    CURLOPT_DNS_CACHE_TIMEOUT => 60,
                ],
            ])
            ->acceptJson()
            ->asJson()
            ->retry(4, 200);
    }

    /**
     * Liefert einen WooClient für den Shop des Produkts (Fallback: first()).
     */
    private function makeClientFor(Product $product): WooClient
    {
        /** @var Shop|null $shop */
        $shop = method_exists($product, 'shop') ? $product->shop : null;
        if (!$shop) {
            $shop = Shop::query()->firstOrFail();
        }
        return new WooClient($shop);
    }

    /**
     * Prüft, ob ein Woo-Produkt mit gegebener ID existiert.
     */
    private function remoteProductExists(Product $product, int $remoteId): bool
    {
        $client = $this->makeClientFor($product);

        try {
            $client->get("products/{$remoteId}");
            return true;
        } catch (ClientException $e) {
            $code = $e->getResponse()?->getStatusCode();
            $body = (string) ($e->getResponse()?->getBody() ?? '');
            if ($code === 404) {
                return false;
            }
            if ($code === 400 && str_contains($body, 'woocommerce_rest_product_invalid_id')) {
                return false;
            }
            throw $e; // andere Client-Fehler weiterreichen
        }
    }

    /**
     * Upsert eines Hauptprodukts: PUT (wenn ID bekannt/gefunden), sonst POST.
     *
     * - Preise werden NICHT synchronisiert.
     * - Für variable Produkte wird die Attributliste aus den Varianten aufgebaut:
     *   [
     *     ['name' => 'pa_size',  'position' => 0, 'visible' => true, 'variation' => true, 'options' => ['S','M','L']],
     *     ['name' => 'pa_color', 'position' => 1, 'visible' => true, 'variation' => true, 'options' => ['Blue','Red']]
     *   ]
     *
     * @param  Product               $product
     * @param  array<string,mixed>   $payload  (ohne Preise)
     * @param  bool                  $failHard
     * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
     */
    public function upsertProduct(Product $product, array $payload, bool $failHard = false): array
    {
        if ($this->isSupplierOutOfStock($product)) {
            Log::info('ProductUpsertService: skipping supplier out-of-stock product', [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
            ]);

            return [
                'action'    => 'skipped',
                'status'    => 0,
                'remote_id' => $product->woo_product_id ? (int) $product->woo_product_id : null,
                'body'      => [
                    'reason' => 'supplier_out_of_stock',
                ],
            ];
        }

        if ($this->isMeterware($product)) {
            Log::info('ProductUpsertService: skipping meterware product', [
                'product_id' => $product->id,
                'product_name' => $product->product_name,
            ]);

            return [
                'action'    => 'skipped',
                'status'    => 0,
                'remote_id' => $product->woo_product_id ? (int) $product->woo_product_id : null,
                'body'      => [
                    'reason' => 'meterware',
                ],
            ];
        }

        // --- Parent-Attribute sicherstellen (deine vorhandene Helper-Methode) ---
        // Mischt 'type' => 'variable' + attributes[] (variation:true, options[]) ins Payload
        // und entfernt Preisfelder am Parent.
        $payload = $this->ensureParentAttributes($product, $payload);

        // --- Name-Resolver (DB-first, Payload darf nicht übersteuern) ---
        $incoming = isset($payload['name']) ? trim((string) $payload['name']) : '';
        $nameFromDb = is_string($product->product_name ?? null) ? trim($product->product_name) : '';

        $incomingIsPlaceholder = ($incoming === '') || (bool) preg_match('/^Product\s*#\s*\d+$/i', $incoming);

        // Regel: DB gewinnt. Wenn product_name existiert, setzen wir ihn immer.
        // Nur wenn product_name leer ist, verwenden wir (falls vorhanden) einen sinnvollen incoming-Namen.
        // Fallback bleibt "Product #<id>".
        if ($nameFromDb !== '') {
            if ($incoming !== '' && $incoming !== $nameFromDb) {
                Log::debug('ProductUpsertService: overriding payload name with DB product_name', [
                    'product_id'   => $product->id ?? null,
                    'incoming'     => $incoming,
                    'db_name'      => $nameFromDb,
                    'incoming_is_placeholder' => $incomingIsPlaceholder,
                ]);
            }
            $payload['name'] = $nameFromDb;
        } else {
            $payload['name'] = !$incomingIsPlaceholder ? $incoming : ('Product #' . ($product->id ?? 'n/a'));
        }

        Log::debug('ProductUpsertService: resolved name for upsert', [
            'product_id'    => $product->id ?? null,
            'resolved_name' => $payload['name'],
            'source'        => $nameFromDb !== '' ? 'db:product_name' : ($incomingIsPlaceholder ? 'fallback' : 'payload.name'),
        ]);

        // --- Vereinheitlichte Upsert-Delegation + Normalisierung ---
        // Wir delegieren an die bestehenden HTTP-Helper, damit alle Requests
        // zentral über $this->http laufen, und normalisieren anschließend die Response.
        $remoteId = (int) ($product->woo_product_id ?? 0);

        // Preflight: Wenn eine ID lokal existiert, prüfe ob sie remote wirklich existiert.
        if ($remoteId > 0) {
            $exists = false;
            try {
                $exists = $this->remoteProductExists($product, $remoteId);
            } catch (\Throwable $e) {
                // defensive: wenn die Probe scheitert, behandeln wie "existiert nicht"
                Log::warning('preflight_exception', [
                    'product_id' => $product->id,
                    'woo_product_id' => $remoteId,
                    'error' => $e->getMessage(),
                ]);
            }
            if (!$exists) {
                Log::warning('preflight_failed_invalid_id', [
                    'product_id' => $product->id,
                    'woo_product_id' => $remoteId,
                ]);
                // lokale ID löschen → Create-Pfad aktivieren
                $product->woo_product_id = null;
                $product->save();
                $remoteId = 0;
            } else {
                Log::debug('preflight_ok', ['product_id' => $product->id, 'woo_product_id' => $remoteId]);
            }
        }

        if ($remoteId > 0) {
            // UPDATE
            $res = $this->updateExisting($remoteId, $product, $payload, $failHard);
        } else {
            // CREATE
            $res = $this->createNew($product, $payload, $failHard);
        }

        // Einheitliches Response-Shape für Aufrufer (BulkAction, Orchestrator, etc.)
        return $this->normalizeUpsertResponse($res);
    }


    /**
     * PUT /products/{id}
     *
     * @param  int                  $wooProductId
     * @param  Product              $product
     * @param  array<string,mixed>  $payload
     * @param  bool                 $failHard
     * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
     */
    protected function updateExisting(int $wooProductId, Product $product, array $payload, bool $failHard): array
    {
        $url = "/products/{$wooProductId}";

        // BEVOR $woo->post(...) oder $woo->put(...):

        $source = app()->runningInConsole() ? 'cli' : 'dashboard';

        Log::debug('ProductUpsertService: upsert payload', [
            'source'   => $source,
            'action'   => isset($wooProductId) ? 'update' : 'create',
            'product_id' => $product->id ?? null,
            'payload'  => $payload,
        ]);


        try {
            $resp = $this->http->put($url, $payload);
            if ($resp->failed()) {
                $this->logHttpError('PUT product', $resp, ['product_id' => $product->id, 'woo_product_id' => $wooProductId]);
                if ($failHard) {
                    $this->throwHttp('PUT product', $resp);
                }
            } else {
                Log::info('ProductUpsertService: product updated', [
                    'product_id' => $product->id,
                    'woo_product_id' => $wooProductId,
                    'status' => $resp->status(),
                ]);
            }

            return [
                'action'    => 'updated',
                'status'    => $resp->status(),
                'remote_id' => $wooProductId,
                'body'      => $resp->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('ProductUpsertService: exception on update', [
                'product_id' => $product->id,
                'woo_product_id' => $wooProductId,
                'error' => $e->getMessage(),
            ]);
            if ($failHard) {
                throw $e;
            }
            return ['action' => 'error', 'status' => 0, 'remote_id' => $wooProductId, 'body' => null];
        }
    }

    /**
     * POST /products
     *
     * @param  Product              $product
     * @param  array<string,mixed>  $payload
     * @param  bool                 $failHard
     * @return array{action:string,status:int,remote_id:int|null,body:array<string,mixed>|null}
     */
    protected function createNew(Product $product, array $payload, bool $failHard): array
    {
        $url = "/products";

        // BEVOR $woo->post(...) oder $woo->put(...):

        $source = app()->runningInConsole() ? 'cli' : 'dashboard';

        Log::debug('ProductUpsertService: upsert payload', [
            'source'   => $source,
            'action'   => isset($wooProductId) ? 'update' : 'create',
            'product_id' => $product->id ?? null,
            'payload'  => $payload,
        ]);


        try {
            $resp = $this->http->post($url, $payload);

            if ($resp->failed()) {
                $this->logHttpError('POST product', $resp, ['product_id' => $product->id]);
                if ($failHard) {
                    $this->throwHttp('POST product', $resp);
                }

                return [
                    'action'    => 'error',
                    'status'    => $resp->status(),
                    'remote_id' => null,
                    'body'      => $resp->json(),
                ];
            }

            $data = $resp->json();
            $remoteId = is_array($data) ? ($data['id'] ?? null) : null;

            if (!empty($remoteId)) {
                $product->woo_product_id = (int) $remoteId;
                $product->save();
            }

            Log::info('ProductUpsertService: product created', [
                'product_id' => $product->id,
                'woo_product_id' => $remoteId,
                'status' => $resp->status(),
            ]);

            return [
                'action'    => 'created',
                'status'    => $resp->status(),
                'remote_id' => $remoteId ? (int) $remoteId : null,
                'body'      => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('ProductUpsertService: exception on create', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
            if ($failHard) {
                throw $e;
            }
            return ['action' => 'error', 'status' => 0, 'remote_id' => null, 'body' => null];
        }
    }

    /**
     * Hilfs-Logging für HTTP-Fehler.
     *
     * @param  string   $action
     * @param  Response $resp
     * @param  array<string,mixed> $ctx
     * @return void
     */
    protected function logHttpError(string $action, Response $resp, array $ctx = []): void
    {
        $body = $resp->json();
        Log::error("ProductUpsertService: {$action} failed", array_merge($ctx, [
            'status' => $resp->status(),
            'body'   => is_array($body) ? $body : $resp->body(),
        ]));
    }

    /**
     * Wirft eine Exception mit Response-Details.
     *
     * @param  string   $action
     * @param  Response $resp
     * @return never
     */
    protected function throwHttp(string $action, Response $resp)
    {
        $body = $resp->json();
        $msg  = is_array($body) ? json_encode($body) : (string) $resp->body();
        throw new \RuntimeException("Woo API {$action} failed: HTTP {$resp->status()} {$msg}");
    }

    /**
     * Stellt sicher, dass alle für den Parent notwendigen Woo-Attribute existieren.
     * - mappt englische Slugs auf deutsche Woo-Attribute (pa_farbe / pa_groessen)
     * - erzeugt Attribute + Terms falls nötig
     * - ersetzt 'name' durch 'id' im Payload
     */
    protected function ensureParentAttributes(Product $product, array $payload): array
    {
        $variantAttributes = $this->collectVariantAttributes($product);
        $attributes = [];
        $pos = 0;

        foreach ($variantAttributes as $slug => $options) {
            // Debug-Hilfe
            Log::debug('ensureParentAttributes: mapping input slug', ['slug' => $slug]);

            // 🇩🇪 Slug-Übersetzung (pa_color -> pa_farbe, pa_size -> pa_groessen)
            [$mappedSlug, $mappedLabel] = $this->mapWooAttributeSlugGerman($slug);

            // Attribut-ID sicherstellen
            $attrId = $this->ensureAttributeId($mappedSlug, $mappedLabel);

            // Terms (Optionen) sicherstellen
            $this->ensureTerms($attrId, $options);

            $attributes[] = [
                'id'        => $attrId,
                'position'  => $pos++,
                'visible'   => true,
                'variation' => true,
                'options'   => array_values(array_unique(array_map([$this, 'normalizeTermOption'], $options))),
            ];

            Log::debug('ensureParentAttributes: mapped slug', ['from' => $slug, 'to' => $mappedSlug]);
        }

        if (!empty($attributes)) {
            $payload['attributes'] = $attributes;
        }

        return $payload;
    }

    /**
     * 🇩🇪 Mappt englische Slugs auf deutsche Woo-Slugs und Labels.
     * Beispiel:
     *  - pa_color  → pa_farbe
     *  - pa_size   → pa_groessen
     */
    private function mapWooAttributeSlugGerman(string $slug): array
    {
        $s = ltrim($slug, '_');
        $s = str_starts_with($s, 'pa_') ? $s : 'pa_' . $s;

        return match ($s) {
            'pa_color', 'pa_colour' => ['pa_farbe', 'Farbe'],
            'pa_size'               => ['pa_groessen', 'Größen'],
            default => [$s, ucfirst(str_replace(['pa_', '_'], ['', ' '], $s))],
        };
    }

    /**
     * Prüft, ob das Attribut existiert, legt es sonst an.
     */
    private function ensureAttributeId(string $slug, string $label): int
    {
        $resp = $this->http->get('products/attributes');
        $list = $resp->successful() ? ($resp->json() ?? []) : [];

        $candidates = [$slug];
        if (str_starts_with($slug, 'pa_')) {
            $candidates[] = substr($slug, 3);
        }

        foreach ($list as $a) {
            $found = (string) ($a['slug'] ?? '');
            if (in_array($found, $candidates, true)) {
                return (int) ($a['id'] ?? 0);
            }
        }

        // nicht gefunden → neu anlegen
        $fixedSlug = str_starts_with($slug, 'pa_') ? $slug : 'pa_' . $slug;
        $create = $this->http->post('products/attributes', [
            'name'         => $label,
            'slug'         => $fixedSlug,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        ]);

        if (!$create->successful()) {
            throw new \RuntimeException("Failed to create attribute {$fixedSlug}: " . $create->body());
        }

        $id = (int) ($create->json()['id'] ?? 0);
        Log::info('woo_attribute_created', ['slug' => $fixedSlug, 'id' => $id]);
        return $id;
    }

    /**
     * Legt fehlende Terms für ein Attribut an.
     */
    private function ensureTerms(int $attributeId, array $options): void
    {
        if (empty($options)) return;

        $list = $this->http->get("products/attributes/{$attributeId}/terms");
        $existing = $list->successful()
            ? collect($list->json() ?? [])->pluck('slug', 'slug')->all()
            : [];

        foreach ($options as $opt) {
            $slug = $this->normalizeTermOption((string) $opt);
            if (isset($existing[$slug])) {
                continue;
            }

            try {
                $resp = $this->http->post("products/attributes/{$attributeId}/terms", [
                    'name' => $slug,
                    'slug' => $slug,
                ])->throw(); // <- wirft bei 4xx/5xx
                Log::info('woo_term_created', ['attribute_id' => $attributeId, 'term' => $slug]);
                // frisch erstellte Terms gleich in die existing-Map aufnehmen (spart Folgedurchläufe)
                $existing[$slug] = $slug;
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $json = $e->response?->json() ?? [];
                $code = (string) (\Illuminate\Support\Arr::get($json, 'code', ''));
                $body = $e->response?->body();

                // ✅ Woo meldet, dass der Begriff bereits existiert → NICHT fatal
                if ($code === 'term_exists' || (\is_string($body) && str_contains($body, 'term_exists'))) {
                    Log::notice('woo_term_exists_ignored', [
                        'attribute_id' => $attributeId,
                        'term'         => $slug,
                    ]);
                    // als vorhanden markieren, damit wir es nicht nochmal versuchen
                    $existing[$slug] = $slug;
                    continue;
                }

                // alles andere weiterwerfen
                throw $e;
            }
        }
    }

    /**
     * Vereinheitlicht Schreibweise (Trim etc.)
     */
    private function normalizeTermOption(string $val): string
    {
        return trim($val);
    }

    /**
     * Liest Parent-Attribute über den WooAttributeResolver (versch. mögliche Methodennamen).
     * Gibt entweder fertige Woo-Attributes zurück, oder baut sie aus einer slug=>options Map.
     *
     * @return array<int,array{name:string,options:array,visible:bool,variation:bool,position?:int}>
     */
    private function getAttributesFromResolver($resolver, \App\Models\Product $product): array
    {
        try {
            // 1) Direkte "fertige" Attribute?
            foreach (['attributesForParent', 'buildParentAttributes', 'resolveParentAttributes'] as $method) {
                if (method_exists($resolver, $method)) {
                    $res = $resolver->{$method}($product);
                    if (is_array($res) && !empty($res)) {
                        // Wir akzeptieren hier bereits die Woo-Shape
                        return array_values($res);
                    }
                }
            }

            // 2) Map slug => options[] und selbst in Woo-Attributes umwandeln
            foreach (['collectVariantAttributes', 'variantOptionsForParent', 'optionsForProduct'] as $method) {
                if (method_exists($resolver, $method)) {
                    $map = $resolver->{$method}($product); // erwarten: ['pa_size'=>['S','M'], ...]
                    if (is_array($map) && !empty($map)) {
                        $attrs = [];
                        $pos = 0;
                        foreach ($map as $slug => $options) {
                            if (!is_array($options) || empty($options)) {
                                continue;
                            }
                            $attrs[] = [
                                'name'      => (string) $slug,
                                'position'  => $pos++,
                                'visible'   => true,
                                'variation' => true,
                                'options'   => array_values(array_unique(array_map('strval', $options))),
                            ];
                        }
                        if (!empty($attrs)) {
                            return $attrs;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Resolver vorhanden, aber lieferte Fehler → Ignorieren, Fallback greift.
            Log::warning('WooAttributeResolver usage failed, falling back to DB aggregation', [
                'product_id' => $product->id,
                'message'    => $e->getMessage(),
            ]);
        }

        return [];
    }


    /**
     * Aggregiert alle Options-Werte je Attribut-Slug auf Basis der echten DB-Struktur:
     * pv (product_variations) → piv (product_variation_attribute_value)
     * → pav (product_attribute_values) → pa (product_attributes)
     *
     * Rückgabe: Map slug => unique options[]
     *   z. B. ['pa_size'=>['S','M','L'], 'pa_color'=>['Blue','Red']]
     */
    private function collectVariantAttributes(\App\Models\Product $product): array
    {
        // Wir arbeiten bewusst mit Query Builder, um keine Relations vorauszusetzen.
        $rows = \Illuminate\Support\Facades\DB::table('product_variations as pv')
            ->join('product_variation_attribute_value as piv', 'piv.product_variation_id', '=', 'pv.id')
            ->join('product_attribute_values as pav', 'pav.id', '=', 'piv.product_attribute_value_id')
            ->join('product_attributes as pa', 'pa.id', '=', 'pav.attribute_id')
            ->where('pv.product_id', $product->id)
            ->select([
                'pa.slug as attr_slug',          // erwarteter Woo-Slug, idealerweise 'pa_*'
                'pav.value as option_value',     // sichtbarer Options-Text
                // 'pav.slug as option_slug',    // falls du Term-Slugs verwenden willst
            ])
            ->get();

        $acc = [];
        foreach ($rows as $r) {
            $slug = (string) ($r->attr_slug ?? '');
            $val  = (string) ($r->option_value ?? '');
            if ($slug === '' || $val === '') {
                continue;
            }
            // Optional: Slug normalisieren (nur wenn nötig)
            // if (!str_starts_with($slug, 'pa_')) { $slug = 'pa_' . $slug; }
            $acc[$slug][] = $val;
        }

        // Deduplizieren / leere entfernen
        $out = [];
        foreach ($acc as $slug => $vals) {
            $vals = array_values(array_unique(array_filter(array_map('strval', $vals), fn($v) => $v !== '')));
            if (!empty($vals)) {
                $out[$slug] = $vals;
            }
        }

        Log::debug('collectVariantAttributes: aggregated from piv/pav/pa', [
            'product_id' => $product->id,
            'attributes' => array_map(fn($v) => count($v), $out), // nur Anzahlen
        ]);

        return $out;
    }


    /**
     * Vereinfacht Namen → Slug (pa_*) für bekannte Attribute.
     */
    private function normalizeAttrSlug(string $name): string
    {
        $n = trim(mb_strtolower($name));
        return match ($n) {
            'color', 'farbe', 'colour' => 'pa_color',
            'size', 'größe', 'groesse', 'gr' => 'pa_size',
            default => $n !== '' ? $n : '',
        };
    }

    /**
     * Vereinheitlicht beliebige Service/HTTP-Responses in ein konsistentes Array.
     * Liefert immer: ['status'=>int, 'id'=>?int, 'remote_id'=>?int, 'action'=>?string, 'body'=>array]
     */
    private function normalizeUpsertResponse(mixed $res): array
    {
        $status = null;
        $id     = null;
        $action = null;
        $body   = null;

        if (is_array($res)) {
            // Häufige Muster aus unseren Services
            $status = $res['status'] ?? $res['code'] ?? null;
            $id     = $res['remote_id'] ?? $res['id'] ?? ($res['body']['id'] ?? null);
            $action = $res['action'] ?? null;
            $body   = $res['body'] ?? $res;
        } elseif (is_object($res)) {
            // HTTP-Wrapper oder stdClass
            if (method_exists($res, 'json')) {
                try {
                    $body = $res->json();
                } catch (\Throwable $e) {
                    $body = null;
                }
            } elseif (property_exists($res, 'body')) {
                $body = is_array($res->body) ? $res->body : json_decode((string) $res->body, true);
            } else {
                // stdClass → in Array kippen
                $body = json_decode(json_encode($res), true);
            }

            if (method_exists($res, 'status')) {
                $status = $res->status();
            } elseif (method_exists($res, 'getStatusCode')) {
                $status = $res->getStatusCode();
            } elseif (is_array($body) && isset($body['status'])) {
                $status = $body['status'];
            }

            $id     = $body['id'] ?? ($res->id ?? ($res->remote_id ?? null));
            $action = $body['action'] ?? ($res->action ?? null);
        }

        $normalized = [
            'status'    => (int) ($status ?? 0),
            'id'        => $id !== null ? (int) $id : null,
            'remote_id' => $id !== null ? (int) $id : null,
            'action'    => is_string($action) ? $action : null,
            'body'      => is_array($body) ? $body : (is_string($body) ? ['raw' => $body] : []),
        ];

        // Debug zur Kontrolle der Normalisierung
        Log::debug('ProductUpsertService: normalized upsert response', [
            'status'    => $normalized['status'],
            'remote_id' => $normalized['remote_id'],
            'action'    => $normalized['action'],
        ]);

        return $normalized;
    }

    private function isSupplierOutOfStock(Product $product): bool
    {
        return $product->meta()
            ->where('scope', 'product')
            ->where('key', 'supplier_out_of_stock')
            ->where('value', '1')
            ->exists();
    }

    protected function isMeterware(Product $product): bool
    {
        $value = $product->meta()
            ->where('meta_key', 'is_meterware')
            ->value('meta_value');

        if ($value === null) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'ja'], true);
    }
}
