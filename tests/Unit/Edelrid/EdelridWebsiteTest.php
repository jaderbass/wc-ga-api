<?php

use App\Services\Edelrid\EdelridWebsite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

/** Nachgebauter Ausschnitt einer Produktseite (Aufbau wie edelrid.com) */
function edelridPage(): string
{
    $variants = htmlspecialchars(json_encode(['76916' => ['76916', [['ONE SIZE', [
        'title' => 'ONE SIZE', 'articleNumber' => '738380000170', 'gtin' => '4028545219165', 'weight' => '234',
    ]]]], '78252' => ['78252', [['ONE SIZE', [
        'title' => 'ONE SIZE', 'articleNumber' => '738380008150', 'gtin' => '4028545204710', 'weight' => '234',
    ]]]]]), ENT_QUOTES);

    $image = fn (string $class, int $id) => '<li class="ed-product-detail-banner-image   '.$class.' " data-product-page-target="carouselItem"><picture>'
        .'<img src="https://shopapi.edelrid.com/v1/media/image/edelrid/'.$id.'/web-s" alt=""></picture></li>';

    $section = fn (string $id, string $title, string $body) => '<li class=" uk-open "><a id="'.$id.'" class="uk-accordion-title uk-flex" href="#">'
        .'<h3><div class="h4">'.$title.'</div></h3></a><div class="uk-accordion-content ed-blog-content ">'.$body."</div>\n</li>";

    return '<html><body>'
        .'<div data-controller="product-detail-description" data-product-detail-description-variants-value="'.$variants.'"></div>'
        .'<ul>'.$image('ed-image-product', 11).$image('ed-image-action', 12).$image('ed-image-product', 13).$image('ed-image-product', 11).'</ul>'
        .'<div class="ed-text-child-light-content">Innovatives Sicherungsger&auml;t</div>'
        .'<ul>'
        .$section('beschreibung', 'Beschreibung', '<p class="x"><strong>PINCH</strong> &ndash; ein neues Sicherungserlebnis. Zweiter Satz.</p>')
        .$section('features', 'Features', '<ul><li>Anti-Panik</li><li>Zweite Bremsstufe</li></ul>')
        .$section('pdf-downloads', 'PDF Downloads', '<ul class="ed-product-downloads"><li><a class="ed-link-plain uk-flex" href="https://avs.edelrid.com/images/attribut/GAL_Pinch.pdf" target="_blank" download="Gebrauchsanleitung Pinch">x</a></li>'
            .'<li><a class="ed-link-plain uk-flex" href="https://avs.edelrid.com/images/attribut/Pinch_EU-Conf.pdf" target="_blank" download="EU-Konformit&auml;tserkl&auml;rung Pinch">x</a></li></ul>')
        .'</ul></body></html>';
}

it('reads variants, texts, pdfs and images from an edelrid product page', function () {
    $page = EdelridWebsite::parse(edelridPage(), 'https://edelrid.com/de-de/professional/abseilgeraete-shop/pinch');

    expect(array_column($page['variants'], 'gtin', 'sku'))->toBe(['738380000170' => '4028545219165', '738380008150' => '4028545204710'])
        ->and($page['variants'][0]['weight'])->toBe(234)
        ->and($page['teaser'])->toBe('Innovatives Sicherungsgerät')
        ->and($page['description'])->toBe('<p><strong>PINCH</strong> – ein neues Sicherungserlebnis. Zweiter Satz.</p>')
        ->and($page['features'])->toContain('<li>Anti-Panik</li>')
        ->and(array_keys($page['pdfs']))->toBe(['Gebrauchsanleitung Pinch', 'EU-Konformitätserklärung Pinch'])
        ->and(array_map(fn ($u) => (int) explode('/', $u)[7], $page['images']))->toBe([11, 13, 12])
        ->and(EdelridWebsite::firstSentence($page['description']))->toBe('PINCH – ein neues Sicherungserlebnis.');
});

it('fills only empty fields of edelrid products', function () {
    Storage::fake('local');
    Http::fake([
        'edelrid.com/sitemap.edelrid.com.products.xml' => Http::response('<urlset><url><loc>https://edelrid.com/de-de/professional/abseilgeraete-shop/pinch</loc></url><url><loc>https://edelrid.com/en-de/professional/x</loc></url></urlset>'),
        'edelrid.com/de-de/professional/*' => Http::response(edelridPage()),
    ]);

    Schema::disableForeignKeyConstraints();
    foreach (['product_meta', 'product_images', 'product_variations', 'products', 'manufacturers'] as $table) {
        Schema::dropIfExists($table);
    }
    Schema::create('manufacturers', fn ($t) => [$t->id(), $t->string('manufacturer'), $t->decimal('purchase_discount_1', 5, 2)->nullable(), $t->decimal('purchase_discount_2', 5, 2)->nullable(), $t->timestamps()]);
    Schema::create('products', fn ($t) => [$t->id(), $t->unsignedBigInteger('manufacturer_id'), $t->string('sku')->nullable(), $t->string('slug')->nullable(), $t->string('product_name')->nullable(), $t->text('description')->nullable(), $t->text('short_description')->nullable(), $t->string('manual_url')->nullable(), $t->string('declaration_of_compliance')->nullable(), $t->boolean('online_sellable')->nullable(), $t->unsignedInteger('list_price_cents')->nullable(), $t->decimal('purchase_discount_1', 5, 2)->nullable(), $t->decimal('purchase_discount_2', 5, 2)->nullable(), $t->unsignedInteger('purchase_price_cents')->nullable(), $t->string('purchase_price_source')->nullable(), $t->timestamps()]);
    Schema::create('product_variations', fn ($t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('sku')->nullable(), $t->string('ean')->nullable(), $t->integer('weight')->nullable(), $t->unsignedInteger('list_price_cents')->nullable(), $t->decimal('purchase_discount_1', 5, 2)->nullable(), $t->decimal('purchase_discount_2', 5, 2)->nullable(), $t->unsignedInteger('purchase_price_cents')->nullable(), $t->string('purchase_price_source')->nullable(), $t->timestamps()]);
    Schema::create('product_images', fn ($t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('url'), $t->boolean('is_main')->default(false), $t->timestamps()]);
    Schema::create('product_meta', fn ($t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('variation_id')->nullable(), $t->string('scope')->nullable(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::enableForeignKeyConstraints();

    DB::table('manufacturers')->insert(['id' => 6, 'manufacturer' => 'Edelrid']);
    DB::table('products')->insert([
        ['id' => 1, 'manufacturer_id' => 6, 'product_name' => 'Pinch', 'description' => null, 'short_description' => null, 'manual_url' => null],
        ['id' => 2, 'manufacturer_id' => 6, 'product_name' => 'Pinch alt', 'description' => '<p>eigener Text</p>', 'short_description' => 'eigen', 'manual_url' => 'https://x/manual.pdf'],
        ['id' => 3, 'manufacturer_id' => 6, 'product_name' => 'Unbekannt', 'description' => null, 'short_description' => null, 'manual_url' => null],
    ]);
    DB::table('product_variations')->insert([
        ['product_id' => 1, 'sku' => '738380000170', 'ean' => null, 'weight' => null],
        ['product_id' => 2, 'sku' => '738380008150', 'ean' => '111', 'weight' => 999],
        ['product_id' => 3, 'sku' => '999999999999', 'ean' => null, 'weight' => null],
    ]);
    DB::table('product_images')->insert(['product_id' => 2, 'url' => 'https://x/eigen.jpg', 'is_main' => true]);

    $this->artisan('edelrid:fetch-media')->assertSuccessful();

    $p1 = DB::table('products')->find(1);
    $p2 = DB::table('products')->find(2);

    expect($p1->short_description)->toBe('Innovatives Sicherungsgerät')
        ->and($p1->description)->toContain('<h4>Features</h4>')
        ->and($p1->manual_url)->toBe('https://avs.edelrid.com/images/attribut/GAL_Pinch.pdf')
        ->and($p1->declaration_of_compliance)->toBe('https://avs.edelrid.com/images/attribut/Pinch_EU-Conf.pdf')
        ->and(DB::table('product_images')->where('product_id', 1)->orderBy('id')->pluck('url')->all())->toBe([
            'https://shopapi.edelrid.com/v1/media/image/edelrid/11/web-l',
            'https://shopapi.edelrid.com/v1/media/image/edelrid/13/web-l',
            'https://shopapi.edelrid.com/v1/media/image/edelrid/12/web-l',
        ])
        ->and(DB::table('product_variations')->where('product_id', 1)->value('ean'))->toBe('4028545219165')
        ->and(DB::table('product_variations')->where('product_id', 1)->value('weight'))->toBe(234)
        // vorhandene Inhalte bleiben
        ->and($p2->description)->toBe('<p>eigener Text</p>')
        ->and($p2->manual_url)->toBe('https://x/manual.pdf')
        ->and(DB::table('product_images')->where('product_id', 2)->count())->toBe(1)
        ->and(DB::table('product_variations')->where('product_id', 2)->value('ean'))->toBe('111')
        ->and(DB::table('products')->find(3)->description)->toBeNull();
});
