<?php

use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\PriceLists\Parsers\AliensPriceListParser;
use App\Services\PriceLists\Parsers\EdelridPriceListParser;
use App\Services\PriceLists\Parsers\PetzlPriceListParser;
use App\Services\PriceLists\PriceListApplier;
use App\Support\Imports\SpreadsheetRowReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach ([
            'category_category_assignment_rule',
            'category_assignment_rules',
            'category_rules',
            'category_product',
            'categories',
            'product_meta',
            'product_variations',
            'products',
            'manufacturers',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('manufacturers', function ($t) {
            $t->id();
            $t->string('manufacturer');
            $t->timestamps();
        });

        Schema::create('products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('product_number')->nullable();
            $t->string('ean')->nullable();
            $t->string('slug')->nullable();
            $t->string('product_name')->nullable();
            $t->string('original_product_name')->nullable();
            $t->text('short_description')->nullable();
            $t->string('source_category')->nullable();
            $t->boolean('online_sellable')->nullable();
            $t->timestamps();
        });

        Schema::create('product_variations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('ean')->nullable();
            $t->integer('weight')->nullable();
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
            $t->string('name')->unique();
            $t->string('slug')->unique();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->timestamps();
        });

        Schema::create('category_product', function ($t) {
            $t->unsignedBigInteger('category_id');
            $t->unsignedBigInteger('product_id');
            $t->string('assignment_type')->default('auto');
            $t->primary(['category_id', 'product_id']);
        });

        Schema::create('category_rules', function ($t) {
            $t->id();
            $t->unsignedBigInteger('category_id');
            $t->string('keyword');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    (require base_path('database/migrations/2026_10_08_100001_create_category_assignment_rules_table.php'))->up();

    DB::table('manufacturers')->insert([
        ['id' => 1, 'manufacturer' => 'Kong Italy'],
        ['id' => 2, 'manufacturer' => 'Edelrid'],
        ['id' => 3, 'manufacturer' => 'Petzl'],
    ]);

    foreach (['Allgemein', 'SALE', 'Statisch', 'Alukarabiner', 'Zubehör', 'Gurte', 'Zubehör Gurte', 'Handschuhe', 'Arbeitsgurt', 'Auffanggurt', 'PSAgA', 'Ersatzteil', 'Zubehör Schutzhelme'] as $name) {
        Category::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name, '-', 'de')]);
    }
});

function aliensSheet(array $rows): array
{
    return array_merge(
        [['Artikelnr.', 'Kurztext', 'HEK netto', 'UVP netto', 'Gewicht', 'Einheit', 'Warentarifnr.', 'Ursprungsland', 'EAN Barcode']],
        $rows,
    );
}

it('parses marker, brand, product type and numbers from the aliens short text', function () {
    $rows = (new AliensPriceListParser)->parse(['Tabelle1' => aliensSheet([
        ['7040XNN', '-- ABVERKAUF -- KONG - Alukarabiner PADDLE - BENT GATE - schwarz', '35', '54,2', '0,135', 'Stk', '76169990', 'IT', '8023577066943'],
        ['TL110', '--AUSVERKAUFT-- 25/03 TEUFELBERGER - Arbeitsseil ORION 500 2,0 - 250 m', '140', '218.49', '7.5', 'Rol', '', 'CZ', ''],
        ['R4', 'TENDON - Reepschnur 4,0 - 100 m schwarz', null, null, null, null, null, null, '0635189687010'],
        ['X', 'SINGING ROCK - Halb-/Zwillingsseil ACCORD 8.3 - blau', null, null, null, null, null, null, null],
        ['Y', 'HELLBERG - Gehörschutz medium für SECURE - gelb', null, null, null, null, null, null, null],
        ['', 'leer', null, null, null, null, null, null, null],
    ])]);

    expect($rows)->toHaveCount(5)
        ->and($rows[0]->marker)->toBe('ABVERKAUF')
        ->and($rows[0]->brand)->toBe('KONG')
        ->and($rows[0]->sourceCategory)->toBe('Alukarabiner')
        ->and($rows[0]->purchasePriceCents)->toBe(3500)
        ->and($rows[0]->retailPriceCents)->toBe(5420)
        ->and($rows[0]->weightGrams)->toBe(135)
        ->and($rows[0]->isClearance())->toBeTrue()
        ->and($rows[1]->marker)->toBe('AUSVERKAUFT 25/03')
        ->and($rows[1]->isUnavailable())->toBeTrue()
        ->and($rows[1]->sourceCategory)->toBe('Arbeitsseil')
        ->and($rows[2]->sourceCategory)->toBe('Reepschnur')
        ->and($rows[2]->ean)->toBe('0635189687010')
        ->and($rows[3]->sourceCategory)->toBe('Halb-/Zwillingsseil')
        ->and($rows[4]->sourceCategory)->toBe('Gehörschutz medium');
});

it('reads xlsx files with shared strings and keeps long numbers intact', function () {
    $path = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="A" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>STATIKSEILE | STATIC ROPES</t></si><si><r><t>Interstatic </t></r><r><t>Protect</t></r></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="2"><c r="A2" t="s"><v>0</v></c></row><row r="3"><c r="A3"><v>832380010170</v></c><c r="C3" t="s"><v>1</v></c></row></sheetData></worksheet>');
    $zip->close();

    $rows = SpreadsheetRowReader::read($path);
    unlink($path);

    expect($rows)->toBe([
        [],
        ['STATIKSEILE | STATIC ROPES'],
        ['832380010170', null, 'Interstatic Protect'],
    ]);
});

it('only reports in a dry run', function () {
    $product = Product::create(['manufacturer_id' => 1, 'product_name' => 'KONG Paddle']);
    ProductVariation::create(['product_id' => $product->id, 'sku' => '7040XNN']);

    $report = app(PriceListApplier::class)->apply(new AliensPriceListParser, ['Tabelle1' => aliensSheet([
        ['7040xnn', 'KONG - Alukarabiner PADDLE - schwarz', '35', '54', '0.1', 'Stk', '', 'IT', '8023577066943'],
        ['NOPE', 'KONG - Stahlkarabiner X - grau', '1', '2', '', '', '', '', ''],
    ])], dryRun: true);

    expect($report->matchedBy)->toBe(['Varianten-SKU' => 1])
        ->and($report->unmatched)->toHaveCount(1)
        ->and(array_values($report->sources)[0]['status'])->toBe('vorschlag')
        ->and(array_values($report->sources)[0]['result'])->toBe('Alukarabiner')
        ->and(CategoryAssignmentRule::count())->toBe(0)
        ->and(DB::table('product_meta')->count())->toBe(0)
        ->and($product->fresh()->source_category)->toBeNull();
});

it('enriches matched products, suggests mappings, handles markers and re-runs cleanly', function () {
    $paddle = Product::create(['manufacturer_id' => 1, 'product_name' => 'KONG Paddle']);
    $variation = ProductVariation::create(['product_id' => $paddle->id, 'sku' => '7040XNN']);
    $rope = Product::create(['manufacturer_id' => 1, 'product_name' => 'Patron 11', 'ean' => '9011800202151']);

    $applier = app(PriceListApplier::class);
    $report = $applier->apply(new AliensPriceListParser, ['Tabelle1' => aliensSheet([
        ['7040XNN', '-- ABVERKAUF -- KONG - Alukarabiner PADDLE - schwarz', '35', '54', '0.135', 'Stk', '7616', 'IT', '8023577066943'],
        ['TL110', '--AUSVERKAUFT-- 25/03 KONG - Statikseil PATRON 11,0 - 100 m', '140', '218', '7.5', 'Rol', '', 'CZ', '9011800202151'],
    ])]);

    $paddle->refresh();
    $rope->refresh();

    expect($report->matchedBy)->toBe(['Varianten-SKU' => 1, 'Produkt-EAN' => 1])
        ->and($paddle->source_category)->toBe('Alukarabiner')
        ->and($variation->fresh()->ean)->toBe('8023577066943')
        ->and($variation->fresh()->weight)->toBe(135)
        ->and($paddle->categories()->pluck('name')->sort()->values()->all())->toBe(['Alukarabiner', 'SALE'])
        ->and(DB::table('category_product')->where('product_id', $paddle->id)->pluck('assignment_type', 'category_id')->sort()->values()->all())->toBe(['auto', 'import'])
        ->and($rope->online_sellable)->toBeFalse()
        ->and($rope->categories()->pluck('name')->all())->toBe(['Statisch'])
        ->and(json_decode(DB::table('product_meta')->where('variation_id', $variation->id)->value('value'), true))
        ->toMatchArray(['article_number' => '7040XNN', 'marker' => 'ABVERKAUF', 'purchase_price_cents' => 3500, 'customs_tariff' => '7616'])
        ->and(CategoryAssignmentRule::where('source_category', 'Alukarabiner')->first()->categories->pluck('name')->all())->toBe(['Alukarabiner']);

    // Abverkauf beendet → SALE (import) wird entfernt, Meta wird aktualisiert statt verdoppelt
    $applier = app(PriceListApplier::class);
    $applier->apply(new AliensPriceListParser, ['Tabelle1' => aliensSheet([
        ['7040XNN', 'KONG - Alukarabiner PADDLE - schwarz', '36', '55', '0.135', 'Stk', '', 'IT', '8023577066943'],
    ])]);

    expect($paddle->categories()->pluck('name')->all())->toBe(['Alukarabiner'])
        ->and(DB::table('product_meta')->where('variation_id', $variation->id)->count())->toBe(1);
});

it('does not overwrite a reviewed mapping with a suggestion', function () {
    $product = Product::create(['manufacturer_id' => 1, 'product_name' => 'KONG Paddle', 'sku' => 'P1']);
    $rule = CategoryAssignmentRule::create(['manufacturer_id' => 1, 'source_category' => 'Alukarabiner', 'is_reviewed' => true]);
    $rule->categories()->sync([Category::where('name', 'Zubehör')->value('id')]);

    app(PriceListApplier::class)->apply(new AliensPriceListParser, ['Tabelle1' => aliensSheet([
        ['P1', 'KONG - Alukarabiner PADDLE - schwarz', '35', '54', '', '', '', '', ''],
    ])]);

    expect($rule->fresh()->categories->pluck('name')->all())->toBe(['Zubehör'])
        ->and($product->categories()->pluck('name')->all())->toBe(['Zubehör']);
});

it('applies edelrid headings and creates the keyword exceptions', function () {
    $harness = Product::create(['manufacturer_id' => 2, 'product_name' => 'Flex Pro', 'sku' => '740010010000']);
    $glove = Product::create(['manufacturer_id' => 2, 'product_name' => 'Work Gloves Open', 'sku' => '722090010000']);
    $jacket = Product::create(['manufacturer_id' => 2, 'product_name' => 'Me Nest Jacket', 'sku' => '722010010000']);
    $other = Product::create(['manufacturer_id' => 1, 'product_name' => 'Kong mit gleicher Nummer', 'sku' => '740010010001']);

    $report = app(PriceListApplier::class)->apply(new EdelridPriceListParser, ['Preise' => [
        [],
        ['GURTE | HARNESSES'],
        ['Artikelnummer Item No.', 'Bezeichnung Description'],
        ['740010010000', 'Flex Pro', 'night (017)', 'S'],
        ['BEKLEIDUNG | CLOTHING'],
        ['722090010000', 'Work Gloves Open', 'black', 'M'],
        ['722010010000', 'Me Nest Jacket', 'black', 'M'],
        ['740010010001', 'nur für Edelrid', null, null],
    ]]);

    expect($report->products)->toBe(3)
        ->and($report->unmatched)->toHaveCount(1)
        ->and($report->keywordRulesCreated)->toBe(count(config('price_lists.edelrid.keyword_rules')))
        ->and($harness->fresh()->source_category)->toBe('GURTE')
        ->and($harness->categories()->pluck('name')->all())->toBe(['Gurte'])
        ->and($glove->categories()->pluck('name')->all())->toBe(['Handschuhe'])
        ->and($jacket->categories()->pluck('name')->all())->toBe([])
        ->and($other->fresh()->source_category)->toBeNull();
});

function petzlSheets(): array
{
    return [
        'Price List' => [
            ['EUROPE'],
            [null, null, null, null, null, 'Unit Price VAT excl.'],
            ['HARNESSES'],
            ['', 'NEWTON®', 'C073AA00', 'Size 0', '', '120.5'],
            [null, null, 'C071AA00', 'Size 1', '', '210'],
        ],
        'Logistic Info' => [
            ['PRO'],
            [],
            ['Status', 'Date status', 'Product name', 'Reference', 'Category', 'Subcategory', 'Description', 'Designation', 'Specifications 1', 'Specifications 2', 'Market', 'Pro', 'EAN Code', 'Customs', 'Made in', "Product  packed\nWeight", 'Unit', 'Volume', 'Unit'],
            ['NEW', '01/03/2027', 'NEWTON® European Version', 'C073AA00', 'HARNESSES', 'Fall-Arrest Harnesses', 'Harness NEWTON', '', '', '', 'PRO', 'VER', '3342540111111', '62179000', 'FR   ', '0.85', 'KG', '9.8', 'CCM'],
            ['', '', 'AVAO® European Version', 'C071AA00', 'HARNESSES', 'Fall-Arrest Harnesses', 'Harness AVAO', '', '', '', 'PRO', 'VER', '3342540222222', '62179000', 'FR', '1.2', 'KG', '9.8', 'CCM'],
            ['EOL', '26/02/2027', 'Foam for VERTEX', 'A010CA90', 'HELMETS', 'Spare Parts for Helmets & Face Shields', 'Foam', '', '', '', 'PRO', 'VER', '3342540333333', '', 'FR', '0.05', 'KG', '', ''],
        ],
        'Contacts' => [['Name']],
    ];
}

it('parses the petzl logistic sheet with status, category and prices', function () {
    $rows = (new PetzlPriceListParser)->parse(petzlSheets());

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->articleNumber)->toBe('C073AA00')
        ->and($rows[0]->sourceCategory)->toBe('HARNESSES > Fall-Arrest Harnesses')
        ->and($rows[0]->marker)->toBe('NEW 01/03/2027')
        ->and($rows[0]->isNew())->toBeTrue()
        ->and($rows[0]->availableFrom)->toBe('2027-03-01')
        ->and($rows[0]->listPriceCents)->toBe(12050)
        ->and($rows[0]->weightGrams)->toBe(850)
        ->and($rows[0]->countryOfOrigin)->toBe('FR')
        ->and($rows[1]->marker)->toBeNull()
        ->and($rows[1]->listPriceCents)->toBe(21000)
        ->and($rows[2]->isUnavailable())->toBeTrue();
});

it('applies the petzl list: newton is a fall-arrest harness, eol leaves the shop, new gets a note', function () {
    $newton = Product::create(['manufacturer_id' => 3, 'product_name' => 'NEWTON® European Version']);
    ProductVariation::create(['product_id' => $newton->id, 'sku' => 'C073AA00']);
    $avao = Product::create(['manufacturer_id' => 3, 'product_name' => 'AVAO® European Version', 'product_number' => 'C071AA00']);
    $foam = Product::create(['manufacturer_id' => 3, 'product_name' => 'Foam for VERTEX', 'product_number' => 'A010CA90']);

    $report = app(PriceListApplier::class)->apply(new PetzlPriceListParser, petzlSheets());

    expect($report->products)->toBe(3)
        ->and($report->newProducts)->toBe(1)
        ->and($report->soldOutProducts)->toBe(1)
        ->and($newton->categories()->pluck('name')->sort()->values()->all())->toBe(['Auffanggurt', 'PSAgA'])
        ->and($avao->categories()->pluck('name')->sort()->values()->all())->toBe(['Arbeitsgurt', 'PSAgA'])
        ->and($foam->categories()->pluck('name')->sort()->values()->all())->toBe(['Ersatzteil', 'Zubehör Schutzhelme'])
        ->and($foam->fresh()->online_sellable)->toBeFalse()
        ->and(DB::table('product_meta')->where('product_id', $newton->id)->where('key', 'pricelist_petzl_availability')->value('value'))
        ->toBe('Neu – lieferbar ab 01.03.2027');

    // NEW-Markierung weg → Hinweis verschwindet
    $sheets = petzlSheets();
    $sheets['Logistic Info'][3][0] = '';
    app(PriceListApplier::class)->apply(new PetzlPriceListParser, $sheets);

    expect(DB::table('product_meta')->where('product_id', $newton->id)->where('key', 'pricelist_petzl_availability')->exists())->toBeFalse();
});
