<?php

use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use App\Models\CategoryRule;
use App\Models\Product;
use App\Services\Categories\ManufacturerCategoryResolver;
use App\Services\Categories\ProductCategorySyncService;
use Database\Seeders\ShopCategoryTreeSeeder;
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
            $t->string('slug')->nullable();
            $t->string('product_name')->nullable();
            $t->string('original_product_name')->nullable();
            $t->text('short_description')->nullable();
            $t->string('petzl_source_category')->nullable();
            $t->string('petzl_source_subcategory')->nullable();
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
        ['id' => 1, 'manufacturer' => 'Edelrid'],
        ['id' => 2, 'manufacturer' => 'Kong'],
    ]);
});

function addSourceCategoryColumn(): void
{
    (require base_path('database/migrations/2026_10_08_100000_add_source_category_to_products_table.php'))->up();
}

function makeCategory(string $name): Category
{
    return Category::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name, '-', 'de')]);
}

function makeRule(array $attributes, array $categories = []): CategoryAssignmentRule
{
    $rule = CategoryAssignmentRule::create($attributes);
    $rule->categories()->sync(collect($categories)->pluck('id')->all());

    return $rule;
}

it('backfills the source category from the petzl columns', function () {
    DB::table('products')->insert([
        ['id' => 1, 'petzl_source_category' => 'Harnesses', 'petzl_source_subcategory' => 'Work positioning'],
        ['id' => 2, 'petzl_source_category' => 'Helmets', 'petzl_source_subcategory' => null],
        ['id' => 3, 'petzl_source_category' => null, 'petzl_source_subcategory' => null],
    ]);

    addSourceCategoryColumn();

    expect(DB::table('products')->orderBy('id')->pluck('source_category', 'id')->all())->toBe([
        1 => 'Harnesses > Work positioning',
        2 => 'Helmets',
        3 => null,
    ]);
});

it('matches keywords at word start, with alternatives and a * wildcard', function () {
    $text = ManufacturerCategoryResolver::normalizeText('KONG - Umlenkrolle EXTRA ROLL - Spring-Ring');

    expect(ManufacturerCategoryResolver::matchesKeyword($text, 'ring'))->toBeTrue()
        ->and(ManufacturerCategoryResolver::matchesKeyword($text, 'pring'))->toBeFalse()
        ->and(ManufacturerCategoryResolver::matchesKeyword($text, 'rolle'))->toBeFalse()
        ->and(ManufacturerCategoryResolver::matchesKeyword($text, '*rolle'))->toBeTrue()
        ->and(ManufacturerCategoryResolver::matchesKeyword($text, 'karabiner | umlenk'))->toBeTrue()
        ->and(ManufacturerCategoryResolver::matchesKeyword($text, ''))->toBeFalse();
});

it('assigns categories from the manufacturer category mapping', function () {
    addSourceCategoryColumn();
    $statisch = makeCategory('Statisch');
    makeRule(['manufacturer_id' => 1, 'source_category' => 'STATIKSEILE'], [$statisch]);

    $product = Product::create([
        'manufacturer_id' => 1,
        'product_name' => 'Performance Static 10.5 mm',
        'source_category' => 'Statikseile',
    ]);

    app(ProductCategorySyncService::class)->sync($product);

    expect($product->categories()->pluck('name')->all())->toBe(['Statisch']);
});

it('lets keyword rules override the mapping, scoped to a source category', function () {
    addSourceCategoryColumn();
    $verbindungsmittel = makeCategory('Verbindungsmittel');
    $schlingen = makeCategory('Schlingen & Lanyard');
    makeRule(['manufacturer_id' => 1, 'source_category' => 'VERBINDUNGSMITTEL'], [$verbindungsmittel]);
    makeRule(['manufacturer_id' => 1, 'source_category' => 'VERBINDUNGSMITTEL', 'keyword' => 'sling'], [$schlingen]);

    $sling = Product::create(['manufacturer_id' => 1, 'product_name' => 'PES Sling 16 mm', 'source_category' => 'VERBINDUNGSMITTEL']);
    $lanyard = Product::create(['manufacturer_id' => 1, 'product_name' => 'Switch Pro', 'source_category' => 'VERBINDUNGSMITTEL']);
    $otherSource = Product::create(['manufacturer_id' => 1, 'product_name' => 'Sling Bag', 'source_category' => 'TRANSPORT']);

    $resolver = new ManufacturerCategoryResolver;

    expect($resolver->resolve($sling)->categories->pluck('name')->all())->toBe(['Schlingen & Lanyard'])
        ->and($resolver->resolve($lanyard)->categories->pluck('name')->all())->toBe(['Verbindungsmittel'])
        ->and($resolver->resolve($otherSource))->toBeNull();
});

it('prefers manufacturer rules over rules for all manufacturers and respects sort order', function () {
    addSourceCategoryColumn();
    $zubehoer = makeCategory('Zubehör');
    $werkzeug = makeCategory('Werkzeugsicherung');
    $segel = makeCategory('Segel-Zubehör');
    $segeln = makeCategory('Segeln');
    makeRule(['manufacturer_id' => null, 'keyword' => 'karabiner', 'sort_order' => 0], [$zubehoer]);
    makeRule(['manufacturer_id' => 2, 'keyword' => 'zubehörkarabiner|materialkarabiner', 'sort_order' => 5], [$werkzeug]);
    makeRule(['manufacturer_id' => 2, 'keyword' => 'gummileine', 'sort_order' => 1], [$segel, $segeln]);

    $resolver = new ManufacturerCategoryResolver;
    $kong = fn (string $name) => Product::create(['manufacturer_id' => 2, 'product_name' => $name]);

    expect($resolver->resolve($kong('KONG - Zubehörkarabiner MINI'))->categories->pluck('name')->all())->toBe(['Werkzeugsicherung'])
        ->and($resolver->resolve($kong('KONG - Gummileine GUMFIX'))->categories->pluck('name')->sort()->values()->all())->toBe(['Segel-Zubehör', 'Segeln'])
        ->and($resolver->resolve(Product::create(['manufacturer_id' => 1, 'product_name' => 'Karabiner X']))->categories->pluck('name')->all())->toBe(['Zubehör']);
});

it('removes automatic categories for excluded products but keeps manual ones', function () {
    addSourceCategoryColumn();
    $allgemein = makeCategory('Allgemein');
    $manual = makeCategory('Handschuhe');
    makeRule(['manufacturer_id' => 1, 'source_category' => 'BEKLEIDUNG', 'exclude' => true]);

    $product = Product::create(['manufacturer_id' => 1, 'product_name' => 'Me Nest Jacket', 'source_category' => 'BEKLEIDUNG']);
    $product->categories()->attach($allgemein->id, ['assignment_type' => 'auto']);
    $product->categories()->attach($manual->id, ['assignment_type' => 'manual']);

    app(ProductCategorySyncService::class)->sync($product);

    expect($product->categories()->pluck('name')->all())->toBe(['Handschuhe']);
});

it('registers unknown source categories as open entries and falls back to keyword rules', function () {
    addSourceCategoryColumn();
    makeCategory('Allgemein');
    $karabiner = makeCategory('Karabiner');
    CategoryRule::create(['category_id' => $karabiner->id, 'keyword' => 'karabiner']);

    $product = Product::create([
        'manufacturer_id' => 2,
        'product_name' => 'KONG - Karabiner FROG',
        'source_category' => 'Connecteurs',
    ]);

    app(ProductCategorySyncService::class)->sync($product);
    app(ProductCategorySyncService::class)->sync($product);

    $open = CategoryAssignmentRule::query()->where('manufacturer_id', 2)->get();

    expect($open)->toHaveCount(1)
        ->and($open->first()->source_category)->toBe('Connecteurs')
        ->and($open->first()->categories)->toBeEmpty()
        ->and($product->categories()->pluck('name')->all())->toBe(['Karabiner']);
});

it('seeds the shop category tree idempotently and keeps existing categories', function () {
    $existing = makeCategory('Karabiner');

    (new ShopCategoryTreeSeeder)->run();
    $countAfterFirstRun = Category::count();
    (new ShopCategoryTreeSeeder)->run();

    $produkte = Category::where('name', 'Produkte')->first();
    $existing->refresh();

    expect(Category::count())->toBe($countAfterFirstRun)
        ->and($existing->parent_id)->toBe($produkte->id)
        ->and(Category::where('name', 'Baumpflege-Zubehör')->value('slug'))->toBe('baumpflege-zubehoer')
        ->and(Category::where('name', 'Höhensicherungsgeräte')->value('parent_id'))->toBe($produkte->id)
        ->and(Category::where('name', 'Segeln')->first()->parent->name)->toBe('Einsatzgebiet')
        ->and(Category::where('name', 'Alukarabiner')->first()->parent_id)->toBe($existing->id);
});
