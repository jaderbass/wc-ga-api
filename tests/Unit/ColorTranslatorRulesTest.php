<?php

use App\Services\ColorTranslator;
use Tests\TestCase;

uses(TestCase::class);

it('joins combinations of basic colors with a hyphen', function (string $input, string $expected) {
    expect(ColorTranslator::auto($input))->toBe($expected);
})->with([
    ['White/Red', 'Weiß-Rot'],
    ['Grau | Orange', 'Grau-Orange'],
    ['Grau/Rot', 'Grau-Rot'],
    ['Black, Yellow', 'Schwarz-Gelb'],
    ['Yellow/Black', 'Gelb-Schwarz'],
    ['black-blue', 'Schwarz-Blau'],
    ['Orange | Rot | Schwarz | Weiß | Grau', 'Orange-Rot-Schwarz-Weiß-Grau'],
    ['Rot | Blau | Grün', 'Rot-Blau-Grün'],
]);

it('translates dark, light and fluorescent colors', function (string $input, string $expected) {
    expect(ColorTranslator::auto($input))->toBe($expected);
})->with([
    ['Dark gray', 'Dunkelgrau'],
    ['light blue', 'Hellblau'],
    ['-Light Grey', 'Hellgrau'],
    ['Orange Fluo', 'Leuchtorange'],
    ['Red Fluo', 'Leuchtrot'],
    ['Yellow Fluo', 'Leuchtgelb'],
    ['HiVis yellow', 'Leuchtgelb'],
    ['neon green', 'Leuchtgrün'],
    ['neon orange', 'Leuchtorange'],
]);

it('drops accessory words after a color', function () {
    expect(ColorTranslator::auto('black with positioning bar'))->toBe('Schwarz')
        ->and(ColorTranslator::auto('gray with positioning bar'))->toBe('Grau')
        ->and(ColorTranslator::auto('with HOOK anchor hook'))->toBeNull();
});

it('leaves special colors and non-colors alone', function (string $input) {
    expect(ColorTranslator::auto($input))->toBeNull();
})->with([
    'Royal Blue', 'oasis', 'oasis-grey', 'blue-night', 'neon green-snow', 'Lime Fluo', 'Light Titanium',
    '10 to 11.5 mm', '100-150 cm', '15 liters', 'MGO - Bm\'D',
]);

it('capitalizes special color names', function (string $input, ?string $expected) {
    expect(ColorTranslator::format($input))->toBe($expected);
})->with([
    ['desert', 'Desert'],
    ['oasis-grey', 'Oasis-Grey'],
    ['blue-night', 'Blue-Night'],
    ['forest green', 'Forest Green'],
    ['Royal Blue', 'Royal Blue'],
    ['BALL-LOCK', 'BALL-LOCK'],
    ['10 to 11.5 mm', null],
    ['100-150 cm', null],
]);
