<?php

use App\Models\ColorTranslation;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Services\ColorTranslator;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::dropIfExists('color_translations');
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

it('keeps special colors as entered when translation equals original', function () {
    ColorTranslation::ensureFor('Royal Blue');
    ColorTranslation::where('source_slug', 'royal-blue')->first()->update(['translated_value' => 'Royal Blue', 'is_reviewed' => true]);

    expect(ColorTranslation::display('Royal Blue'))->toBe('Royal Blue');
});

it('backfills existing color values via colors:sync', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Black', 'slug' => 'black']);
    ColorTranslation::query()->delete();

    $this->artisan('colors:sync')->assertSuccessful();

    expect(ColorTranslation::where('source_slug', 'black')->value('translated_value'))->toBe('Schwarz');
});
