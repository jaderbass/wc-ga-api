<?php

use App\Models\Shop;
use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use App\Services\ShopComparison\ComparisonKey;
use App\Services\ShopComparison\WooSnapshotMatcher;
use App\Services\ShopComparison\WooSnapshotService;
use App\Services\ShopComparison\WooSnapshotStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        Schema::dropIfExists('woo_snapshot_items');
        Schema::dropIfExists('woo_snapshots');
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('products');

        Schema::create('products', function ($t) {
            $t->id();
            $t->string('sku')->nullable();
            $t->string('product_number')->nullable();
            $t->string('ean')->nullable();
            $t->string('product_name')->nullable();
            $t->timestamps();
        });

        Schema::create('product_variations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('sku')->nullable();
            $t->string('ean')->nullable();
            $t->timestamps();
        });

        (require base_path('database/migrations/2026_10_06_100000_create_woo_snapshots_table.php'))->up();
        (require base_path('database/migrations/2026_10_06_100001_create_woo_snapshot_items_table.php'))->up();
    } finally {
        Schema::enableForeignKeyConstraints();
    }
});

function fakeShopApi(array $responses, array &$calls = []): Closure
{
    return function (string $endpoint, array $query) use ($responses, &$calls): array {
        $calls[] = [$endpoint, $query];

        return $responses[$endpoint][$query['page']] ?? [];
    };
}

function shopFixture(): array
{
    return [
        'products' => [1 => [
            ['id' => 10, 'type' => 'simple', 'status' => 'publish', 'name' => 'Seil &amp; Co', 'sku' => 'ABC-1', 'global_unique_id' => '', 'regular_price' => '12.50'],
            ['id' => 20, 'type' => 'variable', 'status' => 'publish', 'name' => 'I’D EVAC', 'sku' => ''],
            ['id' => 30, 'type' => 'simple', 'status' => 'draft', 'name' => 'Nur EAN', 'sku' => 'NOPE', 'global_unique_id' => '04012345678901'],
            ['id' => 40, 'type' => 'simple', 'status' => 'publish', 'name' => 'Doppelt in DB', 'sku' => 'DUP'],
            ['id' => 50, 'type' => 'simple', 'status' => 'publish', 'name' => 'Fehlt', 'sku' => 'MISSING'],
            ['id' => 60, 'type' => 'simple', 'status' => 'publish', 'name' => 'Ohne alles', 'sku' => ''],
        ]],
        'products/20/variations' => [1 => [
            ['id' => 21, 'sku' => 'D020CA00', 'attributes' => [['option' => 'Gelb']], 'meta_data' => [['key' => '_ts_gtin', 'value' => '3342540826670']]],
            ['id' => 22, 'sku' => ' d020ca01 ', 'attributes' => [['option' => 'Schwarz']]],
        ]],
    ];
}

function seedDatabase(): void
{
    DB::table('products')->insert([
        ['id' => 4004, 'sku' => null, 'product_number' => 'D020CA00', 'ean' => null, 'product_name' => 'I’D EVAC'],
        ['id' => 500, 'sku' => null, 'product_number' => 'abc-1', 'ean' => null, 'product_name' => 'Seil'],
        ['id' => 600, 'sku' => null, 'product_number' => 'XYZ', 'ean' => '4012345678901', 'product_name' => 'EAN-Produkt'],
        ['id' => 700, 'sku' => null, 'product_number' => 'P700', 'ean' => null, 'product_name' => 'A'],
        ['id' => 701, 'sku' => null, 'product_number' => 'P701', 'ean' => null, 'product_name' => 'B'],
        ['id' => 800, 'sku' => null, 'product_number' => 'ONLY-DB', 'ean' => null, 'product_name' => 'Nur Datenbank'],
    ]);

    DB::table('product_variations')->insert([
        ['id' => 11153, 'product_id' => 4004, 'sku' => 'D020CA00', 'ean' => '3342540826670'],
        ['id' => 11154, 'product_id' => 4004, 'sku' => 'D020CA01', 'ean' => null],
        ['id' => 7001, 'product_id' => 700, 'sku' => 'DUP', 'ean' => null],
        ['id' => 7011, 'product_id' => 701, 'sku' => 'DUP', 'ean' => null],
    ]);
}

it('normalizes sku and ean keys', function () {
    expect(ComparisonKey::sku(' d020ca00 '))->toBe('D020CA00')
        ->and(ComparisonKey::sku('R-145 OR'))->toBe('R-145OR')
        ->and(ComparisonKey::sku(''))->toBeNull()
        ->and(ComparisonKey::sku(null))->toBeNull()
        ->and(ComparisonKey::ean('08023577054599'))->toBe('8023577054599')
        ->and(ComparisonKey::ean('4028-5451-88805'))->toBe('4028545188805')
        ->and(ComparisonKey::ean('123'))->toBeNull()
        ->and(ComparisonKey::ean('0000000000000'))->toBeNull();
});

it('reads products and variations from the shop with GET pages only', function () {
    $calls = [];
    $snapshot = (new WooSnapshotService(fakeShopApi(shopFixture(), $calls)))->run(new Shop(['name' => 'Test']));

    expect($snapshot->status)->toBe(WooSnapshot::STATUS_COMPLETED)
        ->and($snapshot->products_count)->toBe(6)
        ->and($snapshot->variations_count)->toBe(2)
        ->and(WooSnapshotItem::count())->toBe(8)
        ->and($calls[0])->toBe(['products', ['status' => 'any', 'orderby' => 'id', 'order' => 'asc', 'per_page' => 100, 'page' => 1]]);

    $variation = WooSnapshotItem::where('woo_id', 21)->first();
    expect($variation->type)->toBe('variation')
        ->and($variation->woo_parent_id)->toBe(20)
        ->and($variation->name)->toBe('I’D EVAC – Gelb')
        ->and($variation->ean)->toBe('3342540826670')
        ->and(WooSnapshotItem::where('woo_id', 22)->value('sku_key'))->toBe('D020CA01')
        ->and(WooSnapshotItem::where('woo_id', 10)->value('name'))->toBe('Seil & Co')
        ->and(WooSnapshotItem::where('woo_id', 30)->value('ean_key'))->toBe('4012345678901');
});

it('pages until a page is not full', function () {
    $page1 = array_map(fn ($i) => ['id' => $i, 'type' => 'simple', 'sku' => "S{$i}"], range(1, 100));
    $calls = [];
    $snapshot = (new WooSnapshotService(fakeShopApi(['products' => [1 => $page1, 2 => [['id' => 101, 'type' => 'simple']]]], $calls)))
        ->run(new Shop(['name' => 'Test']));

    expect($snapshot->products_count)->toBe(101)
        ->and(count($calls))->toBe(2);
});

it('marks the snapshot as failed when the shop api fails', function () {
    $service = new WooSnapshotService(fn () => throw new RuntimeException('401 Unauthorized'));

    expect(fn () => $service->run(new Shop(['name' => 'Test'])))->toThrow(RuntimeException::class);

    $snapshot = WooSnapshot::first();
    expect($snapshot->status)->toBe(WooSnapshot::STATUS_FAILED)
        ->and($snapshot->error)->toContain('401');
});

it('matches shop items to the database by sku, then ean, and parents via variations', function () {
    seedDatabase();
    $snapshot = (new WooSnapshotService(fakeShopApi(shopFixture())))->run(new Shop(['name' => 'Test']));

    (new WooSnapshotMatcher)->match($snapshot);

    $item = fn (int $wooId) => WooSnapshotItem::where('woo_id', $wooId)->first();

    expect($item(21)->match_status)->toBe('sku')
        ->and($item(21)->matched_product_id)->toBe(4004)
        ->and($item(21)->matched_variation_id)->toBe(11153)
        ->and($item(22)->matched_variation_id)->toBe(11154)
        ->and($item(20)->match_status)->toBe('variations')
        ->and($item(20)->matched_product_id)->toBe(4004)
        ->and($item(10)->match_status)->toBe('sku')
        ->and($item(10)->matched_product_id)->toBe(500)
        ->and($item(10)->matched_variation_id)->toBeNull()
        ->and($item(30)->match_status)->toBe('ean')
        ->and($item(30)->matched_product_id)->toBe(600)
        ->and($item(40)->match_status)->toBe('ambiguous')
        ->and($item(40)->candidate_count)->toBe(2)
        ->and($item(40)->matched_product_id)->toBeNull()
        ->and($item(50)->match_status)->toBe('shop_only')
        ->and($item(60)->match_status)->toBe('no_key');
});

it('stores no product id when a match is ambiguous', function () {
    DB::table('products')->insert([
        ['id' => 900, 'sku' => null, 'product_number' => 'A900', 'ean' => null, 'product_name' => 'Teil A'],
        ['id' => 901, 'sku' => null, 'product_number' => 'B901', 'ean' => null, 'product_name' => 'Teil B'],
        ['id' => 950, 'sku' => null, 'product_number' => 'C950', 'ean' => null, 'product_name' => 'Zwilling'],
    ]);
    DB::table('product_variations')->insert([
        ['id' => 9001, 'product_id' => 900, 'sku' => 'SPLIT-1', 'ean' => null],
        ['id' => 9011, 'product_id' => 901, 'sku' => 'SPLIT-2', 'ean' => null],
        ['id' => 9501, 'product_id' => 950, 'sku' => 'TWIN', 'ean' => null],
        ['id' => 9502, 'product_id' => 950, 'sku' => 'TWIN', 'ean' => null],
    ]);

    $api = [
        'products' => [1 => [
            ['id' => 70, 'type' => 'variable', 'name' => 'Geteilt', 'sku' => ''],
            ['id' => 80, 'type' => 'simple', 'name' => 'Zwilling', 'sku' => 'TWIN'],
        ]],
        'products/70/variations' => [1 => [
            ['id' => 71, 'sku' => 'SPLIT-1'],
            ['id' => 72, 'sku' => 'SPLIT-2'],
        ]],
    ];
    $snapshot = (new WooSnapshotService(fakeShopApi($api)))->run(new Shop(['name' => 'Test']));
    (new WooSnapshotMatcher)->match($snapshot);

    $item = fn (int $wooId) => WooSnapshotItem::where('woo_id', $wooId)->first();

    expect($item(71)->matched_product_id)->toBe(900)
        ->and($item(72)->matched_product_id)->toBe(901)
        ->and($item(70)->match_status)->toBe('ambiguous')
        ->and($item(70)->matched_product_id)->toBeNull()
        ->and($item(70)->candidate_count)->toBe(2)
        ->and($item(80)->match_status)->toBe('ambiguous')
        ->and($item(80)->matched_product_id)->toBeNull()
        ->and($item(80)->candidate_count)->toBe(2)
        ->and(WooSnapshotStats::for($snapshot)['db_products_matched'])->toBe(2);
});

it('summarizes the comparison including products only in the database', function () {
    seedDatabase();
    $snapshot = (new WooSnapshotService(fakeShopApi(shopFixture())))->run(new Shop(['name' => 'Test']));
    (new WooSnapshotMatcher)->match($snapshot);

    $stats = WooSnapshotStats::for($snapshot);

    expect($stats['units_total'])->toBe(7)
        ->and($stats['units']['sku'])->toBe(3)
        ->and($stats['units']['ean'])->toBe(1)
        ->and($stats['units']['ambiguous'])->toBe(1)
        ->and($stats['units']['shop_only'])->toBe(1)
        ->and($stats['units']['no_key'])->toBe(1)
        ->and($stats['db_products'])->toBe(6)
        ->and($stats['db_products_matched'])->toBe(3)
        ->and($stats['db_products_only'])->toBe(3);
});
