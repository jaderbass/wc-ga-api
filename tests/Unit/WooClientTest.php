<?php

use App\Models\Shop;
use App\Services\Woo\WooClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<int, Response>  $responses
 * @param  array<int, array>  $history
 */
function wooClientWith(array $responses, array &$history): WooClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $shop = new class extends Shop
    {
        protected $casts = [];
    };
    $shop->forceFill([
        'name' => 'Test',
        'base_url' => 'https://shop.test',
        'api_version' => 'wc/v3',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_test',
    ]);

    return new WooClient($shop, new Client(['handler' => $stack, 'base_uri' => 'https://shop.test/wp-json/wc/v3/']));
}

function queryOf(array $entry): string
{
    return $entry['request']->getUri()->getQuery();
}

it('sends a GET request exactly once with basic auth', function () {
    $history = [];
    $client = wooClientWith([new Response(200, [], '[{"id":1}]')], $history);

    expect($client->get('products', ['page' => 1]))->toBe([['id' => 1]])
        ->and($history)->toHaveCount(1)
        ->and($history[0]['request']->getHeaderLine('Authorization'))->toStartWith('Basic ')
        ->and(queryOf($history[0]))->not->toContain('consumer_key');
});

it('keeps throwing the original client exception for a missing product', function () {
    $history = [];
    $client = wooClientWith([new Response(404, [], '{"code":"woocommerce_rest_product_invalid_id"}')], $history);

    expect(fn () => $client->get('products/999'))->toThrow(ClientException::class)
        ->and($history)->toHaveCount(1);
});

it('retries a GET once with query auth after 401', function () {
    $history = [];
    $client = wooClientWith([new Response(401), new Response(200, [], '{"id":5}')], $history);

    expect($client->get('products/5'))->toBe(['id' => 5])
        ->and($history)->toHaveCount(2)
        ->and(queryOf($history[1]))->toContain('consumer_key=ck_test');
});

it('sends a POST request exactly once with query auth', function () {
    $history = [];
    $client = wooClientWith([new Response(201, [], '{"id":42}'), new Response(201, [], '{"id":43}')], $history);

    expect($client->post('products', ['name' => 'Neu']))->toBe(['id' => 42])
        ->and($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and(queryOf($history[0]))->toContain('consumer_key=ck_test');
});

it('never retries a write request after an error', function (int $status, string $exception) {
    $history = [];
    $client = wooClientWith([new Response($status), new Response(200, [], '{}')], $history);

    expect(fn () => $client->put('products/1', ['name' => 'X']))->toThrow($exception)
        ->and($history)->toHaveCount(1);
})->with([
    [401, ClientException::class],
    [500, ServerException::class],
]);
