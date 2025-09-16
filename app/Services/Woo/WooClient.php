<?php

namespace App\Services\Woo;

use App\Models\Shop;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Class WooClient
 *
 * HTTP-Client Wrapper für WooCommerce REST API (wc/v3).
 */
class WooClient
{
    protected Shop $shop;
    protected Client $http;

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


    /** @return array<string,mixed> */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    /** @return array<string,mixed> */
    public function post(string $endpoint, array $json = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $json]);
    }

    /** @return array<string,mixed> */
    public function put(string $endpoint, array $json = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $json]);
    }

    /** @return array<string,mixed> */
    public function delete(string $endpoint, array $query = []): array
    {
        return $this->request('DELETE', $endpoint, ['query' => $query]);
    }

    /** @return array<string,mixed> */
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
