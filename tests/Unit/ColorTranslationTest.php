<?php

use App\Models\ColorTranslation;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariation;
use App\Services\ColorTranslator;
use App\Services\ProductNaming\ProductPropertyExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    ColorTranslation::flushDisplayCache();
    Schema::dropIfExists('color_translations');
    Schema::dropIfExists('product_variation_attribute_value');
    Schema::dropIfExists('product_variations');
    Schema::dropIfExists('product_attribute_values');
    Schema::dropIfExists('product_attributes');

    Schema::create('product_attributes', function ($t) {
        $t->id();
        $t->string('name');
        $t->string('slug')->unique();
        $t->unsignedBigInteger('woo_attribute_id')->nullable();
        $t->timestamps();
    });
    Schema::create('product_attribute_values', function ($t) {
        $t->id();
        $t->unsignedBigInteger('attribute_id');
        $t->string('value');
        $t->string('slug');
        $t->unsignedBigInteger('woo_term_id')->nullable();
        $t->timestamps();
    });

    Schema::create('product_variations', function ($t) {
        $t->id();
        $t->unsignedBigInteger('product_id')->nullable();
        $t->timestamps();
    });

    Schema::create('product_variation_attribute_value', function ($t) {
        $t->unsignedBigInteger('product_variation_id');
        $t->unsignedBigInteger('product_attribute_value_id');
    });

    (require base_path('database/migrations/2026_09_24_090000_create_color_translations_table.php'))->up();
});

it('translates basic colors automatically', function () {
    expect(ColorTranslator::auto('Yellow'))->toBe('Gelb')
        ->and(ColorTranslator::auto('  black '))->toBe('Schwarz')
        ->and(ColorTranslator::auto('Grey'))->toBe('Grau')
        ->and(ColorTranslator::auto('Royal Blue'))->toBeNull()
        ->and(ColorTranslator::auto('White Red'))->toBeNull();
});

it('collects color values on import and auto-translates basic ones', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);

    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Yellow', 'slug' => 'yellow']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Royal Blue', 'slug' => 'royal-blue']);

    $yellow = ColorTranslation::where('source_slug', 'yellow')->first();
    $royal = ColorTranslation::where('source_slug', 'royal-blue')->first();

    expect($yellow->translated_value)->toBe('Gelb')
        ->and($yellow->is_auto)->toBeTrue()
        ->and($royal->translated_value)->toBeNull()
        ->and($royal->is_reviewed)->toBeFalse();
});

it('also collects values of the english color attribute', function () {
    $attr = ProductAttribute::create(['name' => 'color', 'slug' => 'color']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Black', 'slug' => 'black']);

    expect(ColorTranslation::where('source_slug', 'black')->value('translated_value'))->toBe('Schwarz');
});

it('ignores non-color attributes', function () {
    $attr = ProductAttribute::create(['name' => 'Größe', 'slug' => 'grosse']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'XL', 'slug' => 'xl']);

    expect(ColorTranslation::count())->toBe(0);
});

it('does not create duplicates for the same color', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Yellow', 'slug' => 'yellow']);
    ColorTranslation::ensureFor('yellow');
    ColorTranslation::ensureFor('YELLOW ');

    expect(ColorTranslation::count())->toBe(1);
});

it('displays translation, falls back to original and respects manual edits', function () {
    ColorTranslation::ensureFor('Yellow');
    ColorTranslation::ensureFor('White Red');

    expect(ColorTranslation::display('Yellow'))->toBe('Gelb')
        ->and(ColorTranslation::display('White Red'))->toBe('White Red')
        ->and(ColorTranslation::display('Unknown'))->toBe('Unknown');

    ColorTranslation::where('source_slug', 'white-red')->first()->update(['translated_value' => 'Weiß-Rot']);

    expect(ColorTranslation::display('White Red'))->toBe('Weiß-Rot');
});

it('marks a manually edited translation as not automatic', function () {
    ColorTranslation::ensureFor('Yellow');
    $row = ColorTranslation::where('source_slug', 'yellow')->first();
    expect($row->is_auto)->toBeTrue();

    $row->update(['translated_value' => 'Sonnengelb']);

    expect($row->fresh()->is_auto)->toBeFalse()
        ->and(ColorTranslation::display('Yellow'))->toBe('Sonnengelb');
});

it('translates only color attributes via displayFor', function () {
    ColorTranslation::ensureFor('Yellow');

    expect(ColorTranslation::displayFor('Farbe', 'Yellow'))->toBe('Gelb')
        ->and(ColorTranslation::displayFor('pa_color', 'Yellow'))->toBe('Gelb')
        ->and(ColorTranslation::displayFor('size', 'Yellow'))->toBe('Yellow');
});

it('falls back to the original value when the table does not exist yet', function () {
    Schema::dropIfExists('color_translations');
    ColorTranslation::flushDisplayCache();

    expect(ColorTranslation::display('Yellow'))->toBe('Yellow');
});

it('uses the german color in product and variation names', function () {
    ColorTranslation::ensureFor('Yellow');
    ColorTranslation::ensureFor('White Red');
    ColorTranslation::where('source_slug', 'white-red')->first()->update(['translated_value' => 'Weiß-Rot']);

    $makeProduct = function (string $color): Product {
        $product = new Product;
        $variation = new ProductVariation;
        $variation->attributes_json = ['Attribute Group: Seilfarbe' => $color];
        $product->setRelation('variations', collect([$variation]));
        $variation->setRelation('attributeValues', collect([]));

        return $product;
    };

    $extractor = app(ProductPropertyExtractor::class);

    expect($extractor->extract($makeProduct('Yellow'))[0])->toBe('Gelb')
        ->and($extractor->extract($makeProduct('White Red'))[0])->toBe('Weiß-Rot')
        ->and($extractor->extract($makeProduct('Royal Blue'))[0])->toBe('Royal Blue');
});

it('keeps special colors as entered when translation equals original', function () {
    ColorTranslation::ensureFor('Royal Blue');
    ColorTranslation::where('source_slug', 'royal-blue')->first()->update(['translated_value' => 'Royal Blue', 'is_reviewed' => true]);

    expect(ColorTranslation::display('Royal Blue'))->toBe('Royal Blue');
});

it('backfills linked color values via colors:sync', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);
    $value = ProductAttributeValue::create([
        'attribute_id' => $attr->id,
        'value' => 'Black',
        'slug' => 'black',
    ]);

    $variationId = DB::table('product_variations')->insertGetId([
        'product_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_variation_attribute_value')->insert([
        'product_variation_id' => $variationId,
        'product_attribute_value_id' => $value->id,
    ]);

    ColorTranslation::query()->delete();

    $this->artisan('colors:sync')->assertSuccessful();

    expect(ColorTranslation::where('source_slug', 'black')->value('translated_value'))->toBe('Schwarz');
});

it('does not backfill orphaned color values via colors:sync', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);

    ProductAttributeValue::create([
        'attribute_id' => $attr->id,
        'value' => '10 to 11.5 mm',
        'slug' => '10-to-115-mm',
    ]);

    ColorTranslation::query()->delete();

    $this->artisan('colors:sync')->assertSuccessful();

    expect(ColorTranslation::where('source_slug', '10-to-115-mm')->exists())->toBeFalse();
});
