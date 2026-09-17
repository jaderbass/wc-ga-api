<?php

namespace Tests\Unit\ProductNaming;

use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\NameTemplateRegistry;
use App\Services\ProductNaming\ProductKind;
use App\Services\ProductNaming\ProductNameContext;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for building Woo product names based on customer rules.
 *
 * This test does NOT hit the database.
 * It verifies:
 * - manufacturer is uppercased
 * - designation is first-letter uppercased only
 * - separator is " - "
 * - empty parts are skipped
 * - template differs by kind (simple vs variable/set)
 */
class DefaultProductNameBuilderTest extends TestCase
{
    private DefaultProductNameBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure config is deterministic for tests.
        config()->set('product_naming.default.separator', ' - ');
        config()->set('product_naming.default.simple', ['manufacturer', 'category', 'designation', 'p1', 'p2', 'p3']);
        config()->set('product_naming.default.variable', ['manufacturer', 'category', 'designation', 'p1']);
        config()->set('product_naming.default.set', ['manufacturer', 'category', 'designation', 'p1']);

        $this->builder = new DefaultProductNameBuilder(new NameTemplateRegistry);
    }

    #[Test]
    public function it_builds_simple_product_name_with_up_to_three_properties(): void
    {
        $ctx = new ProductNameContext(
            kind: ProductKind::Simple,
            manufacturerName: 'Aliens',
            categoryName: 'Cams',
            designation: 'alien cam', // should become "Alien Cam"
            properties: ['0.5', '90 mm', 'red'],
            manufacturerId: 1,
        );

        $result = $this->builder->build($ctx);

        $this->assertSame('ALIENS - Cams - Alien Cam - 0.5 - 90 mm - red', $result->productName);
    }

    #[Test]
    public function it_builds_variable_product_name_with_only_property1(): void
    {
        $ctx = new ProductNameContext(
            kind: ProductKind::Variable,
            manufacturerName: 'Aliens',
            categoryName: 'Cams',
            designation: 'alien cam',
            properties: ['0.5', 'SHOULD_NOT_APPEAR', 'NOPE'],
            manufacturerId: 1,
        );

        $result = $this->builder->build($ctx);

        $this->assertSame('ALIENS - Cams - Alien Cam - 0.5', $result->productName);
    }

    #[Test]
    public function it_skips_empty_parts_to_avoid_duplicate_separators(): void
    {
        $ctx = new ProductNameContext(
            kind: ProductKind::Simple,
            manufacturerName: 'Aliens',
            categoryName: '', // unknown category -> should be skipped
            designation: 'alien cam',
            properties: ['0.5', null, ''], // p2 null and p3 empty -> skipped
            manufacturerId: 1,
        );

        $result = $this->builder->build($ctx);

        $this->assertSame('ALIENS - Alien Cam - 0.5', $result->productName);
    }

    #[Test]
    public function it_normalizes_designation_words(): void
    {
        $ctx = new ProductNameContext(
            kind: ProductKind::Simple,
            manufacturerName: 'Aliens',
            categoryName: 'Cams',
            designation: 'aLIEN Cam X', // should become "Alien Cam X"
            properties: [],
            manufacturerId: 1,
        );

        $result = $this->builder->build($ctx);

        $this->assertSame('ALIENS - Cams - Alien Cam X', $result->productName);
    }

    #[Test]
    public function it_uses_manufacturer_specific_override_if_configured(): void
    {
        config()->set('product_naming.manufacturers.1.separator', ' | ');
        config()->set('product_naming.manufacturers.1.simple', ['manufacturer', 'designation']);

        $ctx = new ProductNameContext(
            kind: ProductKind::Simple,
            manufacturerName: 'Aliens',
            categoryName: 'Cams',
            designation: 'alien cam',
            properties: ['0.5'],
            manufacturerId: 1,
        );

        $result = $this->builder->build($ctx);

        $this->assertSame('ALIENS | Alien Cam', $result->productName);
    }
}
