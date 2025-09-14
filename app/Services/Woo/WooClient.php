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
        $this->http = new Client([
            'base_uri' => rtrim($shop->base_url, '/') . '/wp-json/' . trim($shop->api_version, '/').'/',
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
    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $options['auth'] = [$this->shop->consumer_key, $this->shop->consumer_secret];

        try {
            $res = $this->http->request($method, ltrim($endpoint, '/'), $options);
            $body = (string) $res->getBody();
            return $body !== '' ? json_decode($body, true) ?? [] : [];
        } catch (GuzzleException $e) {
            throw new RuntimeException('Woo request failed: '.$e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
