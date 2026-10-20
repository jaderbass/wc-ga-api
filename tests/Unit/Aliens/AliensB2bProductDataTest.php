<?php

use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use App\Services\Aliens\AliensB2bProductData;
use Illuminate\Support\Facades\Schema;
use League\Csv\Reader;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['product_meta', 'product_variations', 'products'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('product_number')->nullable();
            $t->string('ean')->nullable();
            $t->string('product_name')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
        });

        Schema::create('product_variations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('ean')->nullable();
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
    } finally {
        Schema::enableForeignKeyConstraints();
    }
});

function b2bRows(): array
{
    $header = ['Produkt ID', 'Produktname', 'Produktreferenz', 'Kombinationsreferenz', 'Produkt-Link', 'Produkt Titelbild', 'Produkt Alle Bild-Links', 'Produktpreis (ohne MwSt.)', 'Kombinationspreis', 'Lange Beschreibung', 'Aktiv', 'EAN-13', 'Name der Standardkategorie', 'Kategorienamen', 'Kombinations-ID', 'Kombination (Attribute)', 'Attributnamen', 'Attributwerte', 'Kombinations-Bild-Links', 'Kombination EAN-13', 'Merkmale', 'Herstellername'];
    $row = fn (array $values) => array_map(fn ($column) => $values[$column] ?? '', $header);

    return [
        $header,
        // vorhanden über Aliens-ID
        $row(['Produkt ID' => '100', 'Produktname' => 'Alt', 'Kombinationsreferenz' => 'A1', 'Kombinations-ID' => '1', 'Aktiv' => '1']),
        // vorhanden über Artikelnummer
        $row(['Produkt ID' => '200', 'Produktname' => 'Bekannt', 'Kombinationsreferenz' => 'K605S', 'Kombinations-ID' => '2', 'Aktiv' => '1']),
        // vorhanden über EAN
        $row(['Produkt ID' => '300', 'Produktname' => 'Per EAN', 'Kombinationsreferenz' => 'X9', 'Kombinations-ID' => '3', 'Kombination EAN-13' => '8023577013565', 'Aktiv' => '1']),
        // neu mit zwei Varianten
        $row(['Produkt ID' => '326', 'Produktname' => 'Abseilachter SMALL', 'Kombinationsreferenz' => 'K605HB', 'Kombinations-ID' => '8073', 'Kombination (Attribute)' => 'Farbe: Hellblau', 'Merkmale' => 'Durchmesser [mm]: Für Ø 8,1 mm bis Ø 11 mm,Mindestbruchlast [kN]: 25', 'Produkt Alle Bild-Links' => 'https://www.aliens-outdoor.com/1/a.jpg, https://www.aliens-outdoor.com/2/b.jpg', 'Lange Beschreibung' => 'Text', 'Herstellername' => 'Aliens', 'Name der Standardkategorie' => 'Sicherungsgeräte', 'Aktiv' => '1']),
        $row(['Produkt ID' => '326', 'Produktname' => 'Abseilachter SMALL', 'Kombinationsreferenz' => 'K605R', 'Kombinations-ID' => '8074', 'Kombination (Attribute)' => 'Farbe: Rot', 'Aktiv' => '1']),
        // neu ohne Kombination
        $row(['Produkt ID' => '917', 'Produktname' => 'Big 8 Edelstahl', 'Produktreferenz' => '805050000KK', 'EAN-13' => '8023577008936', 'Herstellername' => 'Kong Italy', 'Aktiv' => '1']),
        // inaktiv
        $row(['Produkt ID' => '999', 'Produktname' => 'Aus', 'Kombinationsreferenz' => 'Z1', 'Kombinations-ID' => '9', 'Aktiv' => '0']),
        // Stellenanzeige
        $row(['Produkt ID' => '1103', 'Produktname' => 'Initiativbewerbung', 'Name der Standardkategorie' => 'Jobs | Stellenangebote', 'Aktiv' => '1']),
    ];
}

it('splits attribute and feature lists only before "Name: "', function () {
    expect(AliensB2bProductData::pairs('Größe: 18/48,Version allgemein: Rotmessing'))->toBe(['Größe' => '18/48', 'Version allgemein' => 'Rotmessing'])
        ->and(AliensB2bProductData::pairs('Durchmesser [mm]: Für Ø 8,1 mm bis Ø 11 mm,Gewicht: ca. 89 g'))->toBe(['Durchmesser [mm]' => 'Für Ø 8,1 mm bis Ø 11 mm', 'Gewicht' => 'ca. 89 g'])
        ->and(AliensB2bProductData::pairs(''))->toBe([]);
});

it('finds existing products by aliens id, article number and ean', function () {
    $old = Product::create(['product_name' => 'Alt']);
    ProductMeta::create(['product_id' => $old->id, 'scope' => 'product', 'key' => 'aliens_product_id', 'value' => '100']);
    $known = Product::create(['product_name' => 'Bekannt']);
    ProductVariation::create(['product_id' => $known->id, 'sku' => 'K605S']);
    ProductVariation::create(['product_id' => $known->id, 'sku' => 'other', 'ean' => '8023577013565']);

    $groups = AliensB2bProductData::group(b2bRows());

    expect(array_map('strval', array_keys($groups)))->toBe(['100', '200', '300', '326', '917'])
        ->and(AliensB2bProductData::existing($groups))->toBe(['100' => 'Aliens-ID', '200' => 'Artikelnummer', '300' => 'EAN']);
});

it('writes missing products in the legacy aliens export format', function () {
    $groups = AliensB2bProductData::group(b2bRows());
    $path = tempnam(sys_get_temp_dir(), 'b2b');

    $count = AliensB2bProductData::writeLegacyCsv(array_intersect_key($groups, ['326' => 1, '917' => 1]), $path);

    $csv = Reader::createFromPath($path);
    $csv->setDelimiter(';');
    $csv->setHeaderOffset(0);
    $records = iterator_to_array($csv->getRecords(), false);
    unlink($path);

    expect($count)->toBe(3)
        ->and($records[0]['Produkt-ID'])->toBe('326')
        ->and($records[0]['Kombination-ID'])->toBe('8073')
        ->and($records[0]['Kombinations-Referenz'])->toBe('K605HB')
        ->and($records[0]['Attribute Group: Farbe'])->toBe('Hellblau')
        ->and($records[0]['Feature: Durchmesser [mm]'])->toBe('Für Ø 8,1 mm bis Ø 11 mm')
        ->and($records[0]['Feature: Mindestbruchlast [kN]'])->toBe('25')
        ->and($records[0]['Produkt Bilder'])->toContain('/1/a.jpg')
        ->and($records[0]['Hersteller'])->toBe('Aliens')
        ->and($records[1]['Attribute Group: Farbe'])->toBe('Rot')
        ->and($records[1]['Feature: Mindestbruchlast [kN]'])->toBe('')
        // ohne Kombination: eine Variante mit der Produktreferenz
        ->and($records[2]['Kombination-ID'])->toBe('P917')
        ->and($records[2]['Kombinations-Referenz'])->toBe('805050000KK')
        ->and($records[2]['Kombination EAN13'])->toBe('8023577008936');
});

it('collects product and variation image urls once', function () {
    $urls = AliensB2bProductData::imageUrls([
        ['Produkt Alle Bild-Links' => 'https://x.de/1/a.jpg, https://x.de/2/b.jpg', 'Kombinations-Bild-Links' => 'https://x.de/2/b.jpg'],
        ['Produkt Alle Bild-Links' => 'https://x.de/1/a.jpg', 'Kombinations-Bild-Links' => 'https://x.de/3/c.png, https://x.de/seite.html'],
    ]);

    expect($urls)->toBe(['https://x.de/1/a.jpg', 'https://x.de/2/b.jpg', 'https://x.de/3/c.png']);
});

it('maps manufacturer names from the config', function () {
    config()->set('aliens_b2b.manufacturers', ['fujfuj' => 'FUJFUJ', 'chalk' => 'Aliens']);

    expect(AliensB2bProductData::manufacturer(['Herstellername' => 'fujfuj']))->toBe('FUJFUJ')
        ->and(AliensB2bProductData::manufacturer(['Herstellername' => 'Chalk']))->toBe('Aliens')
        ->and(AliensB2bProductData::manufacturer(['Herstellername' => 'Kong Italy']))->toBe('Kong Italy');
});
