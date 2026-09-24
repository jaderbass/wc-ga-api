<?php

use App\Models\ColorTranslation;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Services\ColorTranslator;

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

it('ignores non-color attributes', function () {
    $attr = ProductAttribute::create(['name' => 'Größe', 'slug' => 'grosse']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'XL', 'slug' => 'xl']);

    expect(ColorTranslation::count())->toBe(0);
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

it('backfills existing color values via colors:sync', function () {
    $attr = ProductAttribute::create(['name' => 'Farbe', 'slug' => 'farbe']);
    ProductAttributeValue::create(['attribute_id' => $attr->id, 'value' => 'Black', 'slug' => 'black']);
    ColorTranslation::query()->delete();

    $this->artisan('colors:sync')->assertSuccessful();

    expect(ColorTranslation::where('source_slug', 'black')->value('translated_value'))->toBe('Schwarz');
});
