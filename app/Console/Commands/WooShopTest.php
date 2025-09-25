<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\Woo\WooClient;
use Illuminate\Console\Command;

/**
 * Class WooShopTest
 *
 * Einfache Konnektivitätsprüfung zur WooCommerce REST API.
 * Führt einen GET-Request aus (standardmäßig: products?per_page=1)
 * und gibt einen kurzen Ausschnitt der Antwort aus.
 */
class WooShopTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --shop     Shop-ID oder Name (optional, sonst Default-Shop)
     * --endpoint Zu testendes Endpoint-Fragment (default: products)
     * --per_page Parameter für List-Endpunkte (default: 1)
     */
    protected $signature = 'woo:shop:test
                            {--shop= : Shop-ID oder Name}
                            {--endpoint=products : Endpoint-Fragment, z.B. "products" oder "system_status"}
                            {--per_page=1 : Anzahl Elemente bei List-Endpunkten}';

    /**
     * The console command description.
     */
    protected $description = 'Testet die Verbindung zur WooCommerce-API mit dem konfigurierten Shop.';

    public function handle(): int
    {
        $shop = $this->resolveShop((string) $this->option('shop'));

        $endpoint = trim((string) $this->option('endpoint'), '/');
        $perPage  = (int) $this->option('per_page') ?: 1;

        $this->info("Teste Shop '{$shop->name}' → {$shop->base_url} [API: {$shop->api_version}]");
        $this->line("Endpoint: {$endpoint}");

        try {
            $client = new WooClient($shop);
            $query  = $endpoint === 'products' ? ['per_page' => $perPage] : [];
            $resp   = $client->get($endpoint, $query);

            $this->line('✔ Anfrage erfolgreich.');
            // Kurze, sichere Ausgabe (kein riesiges JSON dumpen)
            $preview = is_array($resp) ? json_encode(array_slice($resp, 0, 1), JSON_UNESCAPED_UNICODE) : (string) $resp;
            $this->line('Vorschau: ' . $preview);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✖ Anfrage fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Resolve Shop by --shop (id or name) or default shop.
     */
    protected function resolveShop(?string $shopOpt): Shop
    {
        $query = Shop::query();
        if ($shopOpt) {
            return ctype_digit($shopOpt)
                ? $query->where('id', (int) $shopOpt)->firstOrFail()
                : $query->where('name', $shopOpt)->firstOrFail();
        }
        return $query->where('is_default', true)->firstOrFail();
    }
}
