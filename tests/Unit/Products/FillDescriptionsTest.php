<?php

use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('fills empty descriptions from petzl texts and short descriptions from the first sentence', function () {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('products');
    Schema::create('products', fn ($t) => [$t->id(), $t->string('product_name')->nullable(), $t->text('description')->nullable(), $t->text('short_description')->nullable(), $t->longText('petzl_description_html')->nullable(), $t->text('petzl_short_description_html')->nullable(), $t->timestamps()]);
    Schema::enableForeignKeyConstraints();

    DB::table('products')->insert([
        ['id' => 1, 'description' => null, 'short_description' => null, 'petzl_description_html' => '<p>Stirnlampe mit 1100 Lumen. Zweiter Satz.</p>', 'petzl_short_description_html' => '<p>Kurz von Petzl</p>'],
        ['id' => 2, 'description' => '<p>Eigene Beschreibung mit Inhalt. Mehr.</p>', 'short_description' => '', 'petzl_description_html' => null, 'petzl_short_description_html' => null],
        ['id' => 3, 'description' => '<p>Bleibt.</p>', 'short_description' => 'Bleibt auch', 'petzl_description_html' => '<p>Petzl</p>', 'petzl_short_description_html' => null],
    ]);

    $this->artisan('products:fill-descriptions')->assertSuccessful();

    expect(DB::table('products')->find(1)->description)->toBe('<p>Stirnlampe mit 1100 Lumen. Zweiter Satz.</p>')
        ->and(DB::table('products')->find(1)->short_description)->toBe('<p>Kurz von Petzl</p>')
        ->and(DB::table('products')->find(2)->short_description)->toBe('Eigene Beschreibung mit Inhalt.')
        ->and(DB::table('products')->find(3)->description)->toBe('<p>Bleibt.</p>')
        ->and(TextNormalizer::firstSentence('<p>Kurz.</p>'))->toBe('Kurz.');
});
