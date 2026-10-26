<?php

use App\Filament\Widgets\ApplicationAreasChartWidget;
use App\Filament\Widgets\AvailabilityWidget;
use App\Filament\Widgets\DashboardKpiWidget;
use App\Filament\Widgets\DataQualityWidget;
use App\Filament\Widgets\DataSourcesWidget;
use App\Filament\Widgets\ManufacturerBarChartWidget;
use App\Filament\Widgets\ManufacturerTilesWidget;
use App\Filament\Widgets\ProductGrowthChartWidget;
use App\Filament\Widgets\RecentProductsGalleryWidget;
use App\Filament\Widgets\ShopCoverageChartWidget;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['woo_snapshot_items', 'woo_snapshots', 'product_meta', 'category_product', 'categories', 'product_images', 'product_variations', 'products', 'manufacturers'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('manufacturers', function ($t) {
            $t->id();
            $t->string('manufacturer');
            $t->string('logo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function ($t) {
            $t->id();
            $t->unsignedBigInteger('manufacturer_id')->nullable();
            $t->string('product_name')->nullable();
            $t->string('product_number')->nullable();
            $t->string('sku')->nullable();
            $t->string('ean')->nullable();
            $t->text('description')->nullable();
            $t->integer('purchase_price_cents')->nullable();
            $t->boolean('online_sellable')->nullable();
            $t->timestamp('petzl_description_fetched_at')->nullable();
            $t->timestamps();
        });
        Schema::create('product_variations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('ean')->nullable();
            $t->integer('purchase_price_cents')->nullable();
            $t->timestamps();
        });
        Schema::create('product_images', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('url');
            $t->boolean('is_main')->default(false);
            $t->timestamps();
        });
        Schema::create('categories', function ($t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->timestamps();
        });
        Schema::create('category_product', function ($t) {
            $t->unsignedBigInteger('category_id');
            $t->unsignedBigInteger('product_id');
            $t->string('assignment_type')->nullable();
        });
        Schema::create('product_meta', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('variation_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('key');
            $t->text('value')->nullable();
            $t->timestamps();
        });

        (require base_path('database/migrations/2026_10_06_100000_create_woo_snapshots_table.php'))->up();
        (require base_path('database/migrations/2026_10_06_100001_create_woo_snapshot_items_table.php'))->up();
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    $now = now();
    DB::table('manufacturers')->insert([['id' => 1, 'manufacturer' => 'Petzl'], ['id' => 2, 'manufacturer' => 'Aliens'], ['id' => 3, 'manufacturer' => 'Leer']]);
    DB::table('products')->insert([
        ['id' => 1, 'manufacturer_id' => 1, 'product_name' => 'I’D S', 'description' => 'Text', 'purchase_price_cents' => 100, 'online_sellable' => true, 'created_at' => $now],
        ['id' => 2, 'manufacturer_id' => 1, 'product_name' => 'Sequoia SRT', 'description' => null, 'purchase_price_cents' => null, 'online_sellable' => false, 'created_at' => $now->copy()->subMonths(3)],
        ['id' => 3, 'manufacturer_id' => 2, 'product_name' => 'Karabiner', 'description' => '', 'purchase_price_cents' => null, 'online_sellable' => null, 'created_at' => $now->copy()->subYears(2)],
    ]);
    DB::table('product_variations')->insert([
        ['product_id' => 1, 'sku' => 'D020AA00', 'purchase_price_cents' => 100, 'created_at' => $now],
        ['product_id' => 2, 'sku' => 'C069BA00', 'purchase_price_cents' => null, 'created_at' => $now],
        ['product_id' => 3, 'sku' => 'K1', 'purchase_price_cents' => 50, 'created_at' => $now],
    ]);
    DB::table('product_images')->insert([
        ['product_id' => 1, 'url' => 'https://example.test/a.jpg', 'is_main' => true, 'created_at' => $now],
        ['product_id' => 2, 'url' => 'https://example.test/b.jpg', 'is_main' => true, 'created_at' => $now],
    ]);
    DB::table('categories')->insert([
        ['id' => 1, 'name' => 'Produkte Übersicht', 'parent_id' => null],
        ['id' => 2, 'name' => 'Produkte', 'parent_id' => 1],
        ['id' => 3, 'name' => 'Abseilgeräte', 'parent_id' => 2],
        ['id' => 4, 'name' => 'Allgemein', 'parent_id' => 2],
        ['id' => 5, 'name' => 'Einsatzgebiet', 'parent_id' => 1],
        ['id' => 6, 'name' => 'PSAgA', 'parent_id' => 5],
        ['id' => 7, 'name' => 'Rettung', 'parent_id' => 5],
    ]);
    DB::table('category_product')->insert([
        ['category_id' => 3, 'product_id' => 1], ['category_id' => 4, 'product_id' => 3],
        ['category_id' => 6, 'product_id' => 1], ['category_id' => 7, 'product_id' => 1], ['category_id' => 7, 'product_id' => 2],
    ]);
    DB::table('product_meta')->insert([
        ['product_id' => 2, 'variation_id' => null, 'key' => 'petzl_discontinued', 'value' => '{}'],
        ['product_id' => 1, 'variation_id' => 1, 'key' => 'petzldealer', 'value' => json_encode(['stock' => 12, 'read_at' => '2026-10-26'])],
    ]);
    $snapshot = DB::table('woo_snapshots')->insertGetId(['shop_id' => 2, 'status' => 'completed', 'created_at' => $now]);
    DB::table('woo_snapshot_items')->insert(['woo_snapshot_id' => $snapshot, 'woo_id' => 5, 'type' => 'simple', 'matched_product_id' => 1]);
});

it('computes the dashboard figures', function () {
    $stats = new DashboardStats;

    expect($stats->totals()['products'])->toMatchArray(['value' => 3, 'added' => 1])
        ->and($stats->totals()['products']['trend'])->toHaveCount(30)
        ->and($stats->totals()['manufacturers']['value'])->toBe(2)
        ->and(array_column($stats->manufacturers(), 'products', 'name'))->toBe(['Petzl' => 2, 'Aliens' => 1])
        ->and($stats->manufacturers()[0])->toMatchArray(['variations' => 2, 'with_image' => 2, 'image_share' => 100])
        ->and(array_column($stats->quality(), 'count', 'label'))->toBe([
            'Titelbild' => 2, 'Beschreibung' => 1, 'Kategorie' => 1, 'Einkaufspreis' => 2, 'Einsatzgebiet' => 2,
        ])
        ->and($stats->applicationAreas())->toBe(['Rettung' => 2, 'PSAgA' => 1])
        ->and($stats->shopCoverage())->toMatchArray(['shop' => 1, 'discontinued' => 1, 'database' => 1])
        ->and($stats->growth()['values'])->toHaveCount(12)
        ->and(last($stats->growth()['values']))->toBe(3)
        ->and($stats->availability())->toMatchArray(['sellable' => 2, 'offline' => 1, 'petzl_in_stock' => 1, 'petzl_out' => 0])
        ->and($stats->sources())->toHaveCount(6);
});

it('renders every dashboard widget', function (string $widget) {
    Livewire::test($widget)->assertOk();
})->with([
    DashboardKpiWidget::class,
    ManufacturerTilesWidget::class,
    ManufacturerBarChartWidget::class,
    DataQualityWidget::class,
    ShopCoverageChartWidget::class,
    ApplicationAreasChartWidget::class,
    ProductGrowthChartWidget::class,
    DataSourcesWidget::class,
    AvailabilityWidget::class,
    RecentProductsGalleryWidget::class,
]);
