<?php

namespace App\Services\Woo;

use App\Models\Shop;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Minimaler WooCommerce-REST-Client:
 * - erzwingt HTTPS für die Base-URL
 * - versucht zuerst Basic-Auth, fällt bei 401 auf Query-Auth (?consumer_key=…&consumer_secret=…) zurück
 */
class WooClient
{
    /** @var Shop Zugehöriger Shop (enthält base_url, api_version, Keys) */
    protected Shop $shop;

    /** @var \GuzzleHttp\Client HTTP-Client für REST-Aufrufe */
    protected Client $http;

    /**
     * @param Shop $shop Shop mit base_url, api_version, consumer_key, consumer_secret
     */
    public function __construct(Shop $shop)
    {
        $this->shop = $shop;

        // HTTPS erzwingen (wichtig für Basic Auth)
        $base = rtrim((string) $shop->base_url, '/');
        $base = preg_replace('#^http:#i', 'https:', $base);

        $ver  = $shop->api_version ?: 'wc/v3';

        $this->http = new \GuzzleHttp\Client([
            'base_uri' => $base . '/wp-json/' . trim($ver, '/') . '/',
            'timeout'  => 30,
        ]);
    }


    /**
     * HTTP GET gegen die Woo REST-API.
     *
     * @param string $resource Relativer Pfad, z. B. 'products' oder 'products/123'
     * @param array<string,mixed> $query Query-Parameter
     * @return array<string,mixed>|list<mixed>|string Decodiertes JSON oder Raw-Body
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /**
     * HTTP POST gegen die Woo REST-API.
     *
     * @param string $resource
     * @param array<string,mixed> $json JSON-Payload
     * @return array<string,mixed>|list<mixed>|string
     */
    public function post(string $endpoint, array $json = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $json]);
    }

    /**
     * HTTP PUT gegen die Woo REST-API.
     *
     * @param string $resource
     * @param array<string,mixed> $json JSON-Payload
     * @return array<string,mixed>|list<mixed>|string
     */
    public function put(string $endpoint, array $json = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $json]);
    }

    /**
     * HTTP DELETE gegen die Woo REST-API.
     *
     * @param string $resource
     * @return array<string,mixed>|list<mixed>|string
     */
    public function delete(string $endpoint, array $query = []): array
    {
        return $this->request('DELETE', $endpoint, ['query' => $query]);
    }

    /**
     * Sendet eine WooCommerce-REST-Anfrage.
     *
     * @param string $method  HTTP-Methode (GET|POST|PUT|DELETE)
     * @param string $resource Relativer Pfad, z. B. "products/123"
     * @param array{
     *   query?: array<string,mixed>,
     *   json?: array<string,mixed>,
     *   headers?: array<string,string>
     * } $opts
     * @return array<string,mixed>|list<mixed>|string  Decodiertes JSON oder Raw-Body
     * @throws \RuntimeException Bei Client-/Serverfehlern (außer 401-Basic→Query-Fallback)
     */
    protected function request(string $method, string $resource, array $opts = [])
    {
        $url = ltrim($resource, '/');

        // Standard-Header
        $opts['headers']['Accept'] = 'application/json';

        // 1) Versuch: HTTPS + Basic Auth
        $optsBasic = $opts + [
            'auth' => [$this->shop->consumer_key, $this->shop->consumer_secret],
        ];

        try {
            $res = $this->http->request($method, $url, $optsBasic);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Nur bei 401 auf Query-Auth fallen
            if ($e->getResponse()?->getStatusCode() !== 401) {
                throw $this->wrap($e);
            }

            // 2) Fallback: Query-Auth
            $optsQuery = $opts;
            $optsQuery['query'] = array_merge($opts['query'] ?? [], [
                'consumer_key'    => $this->shop->consumer_key,
                'consumer_secret' => $this->shop->consumer_secret,
            ]);
            unset($optsQuery['auth']);

            try {
                $res = $this->http->request($method, $url, $optsQuery);
            } catch (\Throwable $e2) {
                throw $this->wrap($e2);
            }
        } catch (\Throwable $e) {
            throw $this->wrap($e);
        }

        $body    = (string) $res->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : $body;
    }

    /**
     * Wickelt Guzzle-Exceptions in eine lesbare RuntimeException mit Response-Snippet.
     *
     * @param \Throwable $e
     * @return \RuntimeException
     */
    protected function wrap(\Throwable $e): \RuntimeException
    {
        $msg = 'Woo request failed: ' . $e->getMessage();
        if ($e instanceof \GuzzleHttp\Exception\ClientException) {
            $resp = $e->getResponse();
            if ($resp) {
                $snippet = substr((string) $resp->getBody(), 0, 600);
                $msg .= ' | body: ' . $snippet;
            }
        }
        return new \RuntimeException($msg, $e->getCode(), $e);
    }
}
