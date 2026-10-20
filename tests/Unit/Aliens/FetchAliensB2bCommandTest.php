<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    config()->set('aliens_b2b.key', 'geheim');
    config()->set('aliens_b2b.base_url', 'https://b2b.example.test/listen/');
});

it('downloads both files with the key and keeps a current copy', function () {
    Http::fake(['b2b.example.test/*' => Http::response("PK\x03\x04xlsx", 200)]);

    $this->artisan('aliens:b2b-fetch')
        ->expectsOutputToContain('Aliens-Produkte.xlsx')
        ->doesntExpectOutputToContain('geheim')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['key'] === 'geheim' && str_contains($request->url(), 'Lagerbestandsliste.xlsx'));
    Storage::disk('local')->assertExists('imports/aliens-b2b/produkte-aktuell.xlsx');
    Storage::disk('local')->assertExists('imports/aliens-b2b/lagerbestand-aktuell.xlsx');
    Storage::disk('local')->assertExists('imports/aliens-b2b/'.now()->format('Y-m-d').'-Lagerbestandsliste.xlsx');
});

it('fails without key and on a login page instead of a file', function () {
    config()->set('aliens_b2b.key', null);
    $this->artisan('aliens:b2b-fetch')->assertFailed();

    config()->set('aliens_b2b.key', 'falsch');
    Http::fake(['b2b.example.test/*' => Http::response('<html>Login</html>', 200)]);

    $this->artisan('aliens:b2b-fetch', ['files' => ['lagerbestand']])
        ->expectsOutputToContain('Abruf fehlgeschlagen')
        ->assertFailed();

    Storage::disk('local')->assertMissing('imports/aliens-b2b/lagerbestand-aktuell.xlsx');
});

it('removes dated downloads older than the retention period', function () {
    Http::fake(['b2b.example.test/*' => Http::response("PK\x03\x04", 200)]);
    Storage::disk('local')->put('imports/aliens-b2b/2020-01-01-Lagerbestandsliste.xlsx', 'alt');

    $this->artisan('aliens:b2b-fetch', ['files' => ['lagerbestand']])->assertSuccessful();

    Storage::disk('local')->assertMissing('imports/aliens-b2b/2020-01-01-Lagerbestandsliste.xlsx');
});
