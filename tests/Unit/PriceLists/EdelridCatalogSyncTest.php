<?php

use App\Services\Edelrid\EdelridCatalogSync;
use App\Services\PriceLists\Parsers\EdelridPriceListParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['category_rules', 'category_category_assignment_rule', 'category_assignment_rules', 'category_product', 'categories', 'product_meta', 'product_variations', 'products', 'manufacturers'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('manufacturers', function ($t) {
            $t->id();
            $t->string('manufacturer');
            $t->decimal('purchase_discount_1', 5, 2)->nullable();
            $t->decimal('purchase_discount_2', 5, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->unsignedBigInteger('author_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('product_number')->nullable();
            $t->string('slug')->nullable();
            $t->string('product_type')->nullable();
            $t->string('product_name')->nullable();
            $t->string('original_product_name')->nullable();
            $t->string('source_category')->nullable();
            $t->boolean('online_sellable')->nullable();
            $t->unsignedInteger('list_price_cents')->nullable();
            $t->decimal('purchase_discount_1', 5, 2)->nullable();
            $t->decimal('purchase_discount_2', 5, 2)->nullable();
            $t->unsignedInteger('purchase_price_cents')->nullable();
            $t->string('purchase_price_source', 20)->nullable();
            $t->timestamps();
        });
        Schema::create('product_variations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('sku')->nullable();
            $t->unsignedInteger('list_price_cents')->nullable();
            $t->decimal('purchase_discount_1', 5, 2)->nullable();
            $t->decimal('purchase_discount_2', 5, 2)->nullable();
            $t->unsignedInteger('purchase_price_cents')->nullable();
            $t->string('purchase_price_source', 20)->nullable();
            $t->json('attributes_json')->nullable();
            $t->timestamps();
        });
        Schema::create('product_meta', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variation_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('categories', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('name_label')->nullable();
            $t->timestamps();
        });
        Schema::create('category_product', function ($t) {
            $t->unsignedBigInteger('category_id');
            $t->unsignedBigInteger('product_id');
            $t->string('assignment_type')->nullable();
        });
        Schema::create('category_assignment_rules', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->string('source_category');
            $t->string('keyword')->nullable();
            $t->boolean('exclude')->default(false);
            $t->boolean('is_reviewed')->default(false);
            $t->timestamps();
        });
        Schema::create('category_rules', function ($t) {
            $t->id();
            $t->unsignedBigInteger('category_id');
            $t->string('keyword');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('category_category_assignment_rule', function ($t) {
            $t->unsignedBigInteger('category_id');
            $t->unsignedBigInteger('category_assignment_rule_id');
        });
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    DB::table('manufacturers')->insert([['id' => 2, 'manufacturer' => 'Edelrid', 'purchase_discount_1' => 35, 'purchase_discount_2' => 0]]);

    $product = fn (int $id, string $name, string $source, array $skus) => [
        DB::table('products')->insert(['id' => $id, 'manufacturer_id' => 2, 'product_name' => $name, 'original_product_name' => $name, 'source_category' => $source, 'online_sellable' => true]),
        DB::table('product_variations')->insert(array_map(fn ($sku) => ['product_id' => $id, 'sku' => $sku], $skus)),
    ];

    $product(1, 'Static Low Stretch 10,5mm', 'STATIKSEILE', ['832720010470', '832720500470']); // bleibt, eine Länge fällt weg
    $product(2, 'Old Rope 9mm', 'STATIKSEILE', ['832990500170']);                            // komplett ausgelaufen
    $product(3, 'Flex Pro', 'GURTE', ['740010010000']);                                       // Verdacht: neue Nummer
    $product(4, 'Boulder Bag', 'SPORT ZUBEHÖR', ['720000010000']);                            // nicht in der Work-Safety-Liste
});

function edelridSheet(): array
{
    return [
        ['STATIKSEILE | STATIC ROPES'],
        ["Artikelnummer\nItem No.", "Bezeichnung\nDescription"],
        ['Static Low Stretch'],
        ['832720010470', 'Static Low Stretch 10,5mm ', '047 snow', '1 M', 'special length*', '', '1,89', 'M', '2,25', 'M'],
        ['832721000470', 'Static Low Stretch 10,5mm II', '047 snow', '100 M', 'spool', '', '189,08', 'PC', '225', 'PC'],
        ['83275 batch*', 'Static Low Stretch 10.5 mm', 'snow (047)', '1 M', 'box', '', '1,56', 'M', '2,25', 'M'],
        ['GURTE | HARNESSES'],
        ['740020010000', 'Flex Pro ', '017 night', 'S', '', '', '100', 'PC', '119', 'PC'],
        ['ZUBEHÖR | ACCESSORIES'],
        ['720830009000', 'Rope Bowl ', '900 assorted colours', '000', '', '', '21,01', 'PC', '25', 'PC'],
        ['720840009000', 'Rope Bowl XL', '900 assorted colours', '000', '', '', '', '', '', ''],
    ];
}

it('reads list prices from the edelrid 2027 list', function () {
    $rows = (new EdelridPriceListParser)->parse(['csv' => edelridSheet()]);

    expect($rows)->toHaveCount(5)
        ->and($rows[0]->listPriceCents)->toBe(189)
        ->and($rows[0]->unit)->toBe('M')
        ->and($rows[1]->listPriceCents)->toBe(18908)
        ->and($rows[4]->listPriceCents)->toBeNull()
        ->and(EdelridPriceListParser::cents('1.125,00'))->toBe(112500);
});

it('reports without changing anything in a dry run', function () {
    $sync = new EdelridCatalogSync;
    $sync->run(EdelridCatalogSync::rows(edelridSheet()), true);

    expect($sync->counts)->toMatchArray([
        'in der Liste' => 5, 'vorhanden' => 1, 'neue Variante' => 1, 'neues Produkt' => 2,
        'Verdacht neue Nummer' => 1, 'Variante ausgelaufen' => 2, 'Produkt ausgelaufen' => 1, 'außerhalb des Bereichs' => 1,
    ])
        ->and(DB::table('product_variations')->count())->toBe(5)
        ->and(DB::table('product_meta')->count())->toBe(0);
});

it('marks discontinued items, adds new ones and leaves suspects and other ranges alone', function () {
    (new EdelridCatalogSync)->run(EdelridCatalogSync::rows(edelridSheet()), false);

    $meta = DB::table('product_meta')->where('key', EdelridCatalogSync::META)->get();

    expect(DB::table('products')->where('id', 2)->value('online_sellable'))->toBe(0)
        ->and(DB::table('products')->where('id', 1)->value('online_sellable'))->toBe(1)
        ->and(DB::table('products')->where('id', 3)->value('online_sellable'))->toBe(1)   // Verdacht: unverändert
        ->and(DB::table('products')->where('id', 4)->value('online_sellable'))->toBe(1)   // Sport: unverändert
        ->and($meta->whereNull('variation_id')->pluck('product_id')->all())->toBe([2])
        ->and($meta->whereNotNull('variation_id')->count())->toBe(2)
        ->and(DB::table('product_variations')->where('sku', '832721000470')->value('product_id'))->toBe(1)
        ->and(DB::table('products')->where('product_name', 'Rope Bowl')->value('source_category'))->toBe('ZUBEHÖR')
        ->and(DB::table('product_variations')->where('sku', '720830009000')->value('list_price_cents'))->toBe(2101)
        ->and(DB::table('products')->where('product_name', 'Flex Pro')->count())->toBe(1);

    // Artikel taucht wieder auf → Produkt wieder aktiv, Meta weg
    $rows = [...edelridSheet(), ['STATIKSEILE | STATIC ROPES'], ['832990500170', 'Old Rope 9mm', '017 night', '50 M', 'spool', '', '80', 'PC', '95', 'PC']];
    $sync = new EdelridCatalogSync;
    $sync->run(EdelridCatalogSync::rows($rows), false);

    expect($sync->counts['wieder aktiv'])->toBe(1)
        ->and(DB::table('products')->where('id', 2)->value('online_sellable'))->toBe(1)
        ->and(DB::table('product_meta')->where('product_id', 2)->where('key', EdelridCatalogSync::META)->count())->toBe(0);
});

it('creates successors as new products and links them when asked', function () {
    $sync = new EdelridCatalogSync(successorsAsNew: true);
    $sync->run(EdelridCatalogSync::rows(edelridSheet()), false);

    $new = DB::table('products')->where('product_name', 'Flex Pro')->where('id', '!=', 3)->value('id');

    expect($sync->counts['Verdacht neue Nummer'])->toBe(1)
        ->and($new)->not->toBeNull()
        ->and(DB::table('products')->where('id', 3)->value('online_sellable'))->toBe(0)
        ->and(DB::table('product_meta')->where('product_id', 3)->where('key', 'edelrid_successor')->value('value'))->toBe((string) $new)
        ->and(DB::table('product_meta')->where('product_id', $new)->where('key', 'edelrid_predecessor')->value('value'))->toBe('3');
});
