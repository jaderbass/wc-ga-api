<?php

use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use App\Services\Petzl\PetzlDealerImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['category_product', 'categories', 'product_meta', 'product_variations', 'products', 'manufacturers'] as $table) {
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
            $t->string('slug')->nullable();
            $t->string('product_name')->nullable();
            $t->string('original_product_name')->nullable();
            $t->string('product_type')->nullable();
            $t->string('source_category')->nullable();
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
            $t->text('attributes_json')->nullable();
            $t->unsignedInteger('list_price_cents')->nullable();
            $t->decimal('purchase_discount_1', 5, 2)->nullable();
            $t->decimal('purchase_discount_2', 5, 2)->nullable();
            $t->unsignedInteger('purchase_price_cents')->nullable();
            $t->string('purchase_price_source', 20)->nullable();
            $t->timestamps();
        });

        Schema::create('product_meta', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variation_id')->nullable();
            $t->string('scope')->default('product');
            $t->string('key');
            $t->longText('value')->nullable();
            $t->timestamps();
        });

        Schema::create('categories', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->timestamps();
        });

        Schema::create('category_product', function ($t) {
            $t->unsignedBigInteger('category_id');
            $t->unsignedBigInteger('product_id');
            $t->string('assignment_type')->default('auto');
        });
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    DB::table('manufacturers')->insert(['id' => 3, 'manufacturer' => 'Petzl', 'purchase_discount_1' => 35, 'purchase_discount_2' => 5]);
    DB::table('categories')->insert([['name' => 'Stirnlampe'], ['name' => 'Stirnlampe-Zubehör']]);
});

function petzlDealerJson(): string
{
    return json_encode([
        'read_at' => '2026-10-26',
        'columns' => ['bereich', 'produkt', 'artikelnummer', 'variante', 'neu', 'auslauf', 'uvp_netto', 'lager_stueck', 'naechster_wareneingang', 'kategorie'],
        'rows' => [
            // vorhanden (Leerzeichen in der Nummer egal)
            ['Professional/Stirnlampen', 'PIXA®', 'E120AA00', 'Schwarz, Gelb', 0, 0, 55, 8551, '04.11.2026', 'Stirnlampe'],
            // neue Farbe eines vorhandenen Produkts (gleiche Stammnummer E068BA)
            ['Sport/Ersatzteile', 'Kopfband ARIA E068BA', 'E068BA02', 'CAMO', 0, 0, 7, 255, '17.02.2027', 'Stirnlampe-Zubehör'],
            // neues Produkt mit zwei Farben
            ['Sport/Stirnlampen', 'TIKKA®', 'E061AB00', 'BLACK', 0, 0, 29.17, null, '05.11.2026', 'Stirnlampe'],
            ['Sport/Stirnlampen', 'TIKKA®', 'E061AB01', 'BLUE', 0, 0, 29.17, 2143, '22.12.2026', 'Stirnlampe'],
            // fehlender Auslaufartikel → nicht anlegen
            ['Auslauf/Professional', 'ACCU 2 DUO Z1', 'E80001', '', 0, 1, 75, 19, '', 'Stirnlampe-Zubehör'],
        ],
    ]);
}

it('enriches existing, adds new colours to existing products and creates new products', function () {
    $pixa = Product::query()->forceCreate(['manufacturer_id' => 3, 'product_name' => 'PIXA®', 'slug' => 'pixa']);
    $pixaVariation = ProductVariation::query()->forceCreate(['product_id' => $pixa->id, 'sku' => 'E120AA00', 'list_price_cents' => 5500]);
    $headband = Product::query()->forceCreate(['manufacturer_id' => 3, 'product_name' => 'Headband ARIA®', 'slug' => 'headband-aria']);
    ProductVariation::query()->forceCreate(['product_id' => $headband->id, 'sku' => 'E068BA01']);

    $import = new PetzlDealerImport;
    $import->run(PetzlDealerImport::rows(petzlDealerJson()), false, null, '2026-10-26');

    expect($import->counts)->toBe(['vorhanden' => 1, 'neue Variante' => 1, 'neues Produkt' => 1, 'Auslauf übersprungen' => 1]);

    // vorhandenes unverändert, nur Meta
    expect($pixaVariation->fresh()->list_price_cents)->toBe(5500)
        ->and(json_decode(ProductMeta::where('variation_id', $pixaVariation->id)->where('key', 'petzldealer')->value('value'), true)['stock'])->toBe(8551);

    // neue Farbe hängt am vorhandenen Kopfband
    $camo = ProductVariation::where('sku', 'E068BA02')->first();
    expect($camo->product_id)->toBe($headband->id)
        ->and($camo->attributes_json)->toBe(['Attribute Group: Farbe' => 'CAMO']);

    // neues Produkt TIKKA mit zwei Varianten, Listenpreis = UVP, EK mit 35 % + 5 %
    $tikka = Product::where('original_product_name', 'TIKKA®')->first();
    expect($tikka->variations()->pluck('sku')->all())->toBe(['E061AB00', 'E061AB01'])
        ->and($tikka->source_category)->toBe('PetzlDealer: Sport/Stirnlampen')
        ->and($tikka->categories()->pluck('name')->all())->toBe(['Stirnlampe'])
        ->and(ProductVariation::where('sku', 'E061AB00')->value('list_price_cents'))->toBe(2917)
        ->and(ProductVariation::where('sku', 'E061AB00')->value('purchase_price_cents'))->toBe(1801)
        ->and(ProductVariation::where('sku', 'E80001')->exists())->toBeFalse();
});

it('changes nothing in a dry run', function () {
    $import = new PetzlDealerImport;
    $import->run(PetzlDealerImport::rows(petzlDealerJson()), true);

    expect($import->counts['neues Produkt'])->toBe(3)
        ->and(Product::count())->toBe(0)
        ->and(ProductMeta::count())->toBe(0);
});
