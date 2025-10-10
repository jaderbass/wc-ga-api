<?php

namespace App\Services\Woo;

use App\Models\Shop;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException; // <-- hinzufügen
use RuntimeException;

class WooClient
{
    protected Shop $shop;
    protected Client $http;

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;

        $base = rtrim((string) $shop->base_url, '/');
        $base = preg_replace('#^http:#i', 'https:', $base);
        $ver  = $shop->api_version ?: 'wc/v3';

        $this->http = new Client([
            'base_uri'         => $base . '/wp-json/' . trim($ver, '/') . '/',
            'timeout'          => 30,
            'connect_timeout'  => 10,              // <— schneller Fail bei DNS/Netz
            'force_ip_resolve' => 'v4',            // <— Guzzle-eigener Schalter
            'curl'             => [
                CURLOPT_IPRESOLVE         => CURL_IPRESOLVE_V4, // <— cURL-Seite
                CURLOPT_DNS_CACHE_TIMEOUT => 60,
            ],
        ]);
    }

    public function get(string $endpoint, array $query = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }
    public function post(string $endpoint, array $json = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $json]);
    }
    public function put(string $endpoint, array $json = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $json]);
    }
    public function delete(string $endpoint, array $query = []): array
    {
        return $this->request('DELETE', $endpoint, ['query' => $query]);
    }

    protected function request(string $method, string $resource, array $opts = [])
    {
        $url = ltrim($resource, '/');
        $opts['headers']['Accept'] = 'application/json';

        $optsBasic = $opts + [
            'auth' => [$this->shop->consumer_key, $this->shop->consumer_secret],
        ];

        try {
            $res = $this->http->request($method, $url, $optsBasic);
        } catch (ClientException $e) {
            if ($e->getResponse()?->getStatusCode() !== 401) {
                throw $this->wrap($e, $method, $url);
            }
            $optsQuery = $opts;
            $optsQuery['query'] = array_merge($opts['query'] ?? [], [
                'consumer_key'    => $this->shop->consumer_key,
                'consumer_secret' => $this->shop->consumer_secret,
            ]);
            unset($optsQuery['auth']);
            try {
                $res = $this->http->request($method, $url, $optsQuery);
            } catch (\Throwable $e2) {
                throw $this->wrap($e2, $method, $url);
            }
        } catch (\Throwable $e) {
            throw $this->wrap($e, $method, $url);
        }

        $body    = (string) $res->getBody();
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : $body;
    }

    protected function wrap(\Throwable $e, string $method, string $endpoint): RuntimeException
    {
        $msg = sprintf(
            'Woo request failed: %s %s → %s',
            $method,
            $endpoint,
            $e->getMessage()
        );

        if ($e instanceof ClientException && $e->getResponse()) {
            $snippet = substr((string) $e->getResponse()->getBody(), 0, 600);
            $msg .= ' | body: ' . $snippet;
        }

        // Extra Hinweis bei DNS/Connect-Fehlern
        if ($e instanceof ConnectException) {
            $msg .= sprintf(' | base_uri=%s', (string) $this->http->getConfig('base_uri'));
        }

        return new RuntimeException($msg, (int)$e->getCode(), $e);
    }
}
