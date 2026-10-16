<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductNaming\ProductNameCategory;
use Database\Seeders\ShopCategoryTreeSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['category_rules', 'category_product', 'categories', 'products'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->string('sku')->nullable();
            $t->string('slug')->nullable();
            $t->string('product_name')->nullable();
            $t->string('original_product_name')->nullable();
            $t->text('short_description')->nullable();
            $t->timestamps();
        });

        Schema::create('categories', function ($t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('name_label')->nullable();
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

    Category::create(['name' => 'Allgemein', 'slug' => 'allgemein']);
    (new ShopCategoryTreeSeeder)->run();
});

function productWith(array $categories, array $manual = []): Product
{
    $product = Product::create(['product_name' => 'Test', 'original_product_name' => 'Test Produkt']);

    foreach ($categories as $name) {
        $product->categories()->attach(
            Category::where('name', $name)->value('id'),
            ['assignment_type' => in_array($name, $manual, true) ? 'manual' : 'auto'],
        );
    }

    return $product;
}

it('uses the most specific product category and ignores Einsatzgebiete, SALE and Allgemein', function () {
    $harness = productWith(['PSAgA', 'Arbeitsgurt', 'SALE']);
    $spare = productWith(['Ersatzteil', 'Zubehör Gurte']);

    expect(ProductNameCategory::nameFor($harness, 'Avao'))->toBe('Arbeitsgurt')
        ->and(ProductNameCategory::nameFor($spare, 'Spare'))->toBe('Zubehör Gurte')
        ->and(ProductNameCategory::primary(productWith(['Allgemein', 'Segeln'])))->toBeNull();
});

it('prefers manually assigned categories and uses the name label', function () {
    Category::where('name', 'Statisch')->update(['name_label' => 'Statikseil']);

    $rope = productWith(['Statisch']);
    $manual = productWith(['Statisch', 'Zubehör'], manual: ['Zubehör']);

    expect(ProductNameCategory::nameFor($rope, 'Canyon Dry'))->toBe('Statikseil')
        ->and(ProductNameCategory::nameFor($manual, 'Canyon Dry'))->toBe('Zubehör');
});

it('returns the product group below "Produkte" for the lead property', function () {
    expect(ProductNameCategory::leadGroupFor(productWith(['Statisch']), 'x'))->toBe('Seile')
        ->and(ProductNameCategory::leadGroupFor(productWith(['Alukarabiner']), 'x'))->toBe('Karabiner')
        ->and(ProductNameCategory::leadGroupFor(productWith(['Handschuhe']), 'x'))->toBe('Handschuhe');
});

it('falls back to the keyword rules when the product has no usable category', function () {
    $product = productWith([]);

    expect(ProductNameCategory::nameFor($product, 'Irgendwas'))->toBe('Allgemein')
        ->and(ProductNameCategory::nameFor(new Product, 'Irgendwas'))->toBe('Allgemein');
});

it('ignores old categories outside the shop structure, also in the keyword fallback', function () {
    $old = Category::create(['name' => 'Kategorie TEST', 'slug' => 'kategorie-test']);
    App\Models\CategoryRule::create(['category_id' => $old->id, 'keyword' => 'test']);

    $onlyOld = productWith(['Kategorie TEST']);
    $mixed = productWith(['Kategorie TEST', 'Sitzgurt']);

    expect(ProductNameCategory::nameFor($onlyOld, 'Test Produkt'))->toBe('Allgemein')
        ->and(ProductNameCategory::nameFor($mixed, 'Test Produkt'))->toBe('Sitzgurt');
});
