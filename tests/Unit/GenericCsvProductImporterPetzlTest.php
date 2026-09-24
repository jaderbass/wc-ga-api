<?php

use App\Importers\GenericCsvProductImporter;
use Tests\TestCase;

uses(TestCase::class);

class TestableGenericCsvProductImporter extends GenericCsvProductImporter
{
    public function parseRopeSpec(string $value): array
    {
        return $this->parsePetzlRopeSpec($value);
    }
}

it('parses valid Petzl rope type and diameter specifications', function () {
    $importer = new TestableGenericCsvProductImporter('petzl', 3);

    expect($importer->parseRopeSpec('AXIS 11 mm'))
        ->toBe([
            'type' => 'AXIS',
            'diameter' => '11 mm',
        ])
        ->and($importer->parseRopeSpec('PARALLEL 10.5 mm'))
        ->toBe([
            'type' => 'PARALLEL',
            'diameter' => '10.5 mm',
        ]);
});

it('does not parse Petzl diameter ranges as rope type and diameter', function () {
    $importer = new TestableGenericCsvProductImporter('petzl', 3);

    expect($importer->parseRopeSpec('10 to 11.5 mm'))->toBe([])
        ->and($importer->parseRopeSpec('10.5 to 11.5 mm'))->toBe([])
        ->and($importer->parseRopeSpec('11.5 to 13 mm'))->toBe([])
        ->and($importer->parseRopeSpec('10-11.5 mm'))->toBe([]);
});
