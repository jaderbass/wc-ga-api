<?php

use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use App\Services\ShopComparison\WooSnapshotImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        Schema::dropIfExists('woo_snapshot_items');
        Schema::dropIfExists('woo_snapshots');
        Schema::dropIfExists('product_images');

        Schema::create('product_images', function ($t) {
            $t->id();
            $t->unsignedBigInteger('product_id');
            $t->string('url');
            $t->boolean('is_main')->default(false);
            $t->timestamps();
        });

        (require base_path('database/migrations/2026_10_06_100000_create_woo_snapshots_table.php'))->up();
        (require base_path('database/migrations/2026_10_06_100001_create_woo_snapshot_items_table.php'))->up();
    } finally {
        Schema::enableForeignKeyConstraints();
    }
});

function snapshotWithImages(): WooSnapshot
{
    $snapshot = WooSnapshot::create(['shop_id' => 2, 'status' => WooSnapshot::STATUS_COMPLETED, 'matched_at' => now()]);
    $img = fn (string $file) => ['src' => 'https://shop.example/wp-content/uploads/'.$file];

    foreach ([
        // Variante steht vor dem Hauptprodukt – Titelbild soll trotzdem das des Hauptprodukts sein
        ['woo_id' => 21, 'woo_parent_id' => 20, 'matched_product_id' => 3939, 'payload' => ['image' => $img('srt-gelb.jpg')]],
        ['woo_id' => 20, 'woo_parent_id' => null, 'matched_product_id' => 3939, 'payload' => ['images' => [$img('srt.jpg'), $img('srt-2.jpg'), $img('srt.jpg')]]],
        ['woo_id' => 30, 'woo_parent_id' => null, 'matched_product_id' => 4000, 'payload' => ['images' => [$img('hat-schon.jpg')]]],
        ['woo_id' => 40, 'woo_parent_id' => null, 'matched_product_id' => 4100, 'payload' => ['images' => [$img('woocommerce-placeholder.png')]]],
        ['woo_id' => 50, 'woo_parent_id' => null, 'matched_product_id' => null, 'payload' => ['images' => [$img('nur-im-shop.jpg')]]],
    ] as $row) {
        WooSnapshotItem::create($row + ['woo_snapshot_id' => $snapshot->id, 'type' => 'simple', 'name' => 'Produkt '.$row['woo_id']]);
    }

    DB::table('product_images')->insert(['product_id' => 4000, 'url' => 'https://www.petzl.com/x.jpg', 'is_main' => true]);

    return $snapshot;
}

it('takes shop images only for matched products without images, main image first', function () {
    $images = new WooSnapshotImages;
    $images->run(snapshotWithImages(), false);

    expect(DB::table('product_images')->where('product_id', 3939)->orderBy('id')->get(['url', 'is_main'])->map(fn ($r) => [basename($r->url), (bool) $r->is_main])->all())
        ->toBe([['srt.jpg', true], ['srt-2.jpg', false], ['srt-gelb.jpg', false]])
        ->and(DB::table('product_images')->where('product_id', 4000)->count())->toBe(1)
        ->and(DB::table('product_images')->where('product_id', 4100)->count())->toBe(0)
        ->and($images->counts)->toBe(['Produkte mit Shop-Bildern' => 1, 'schon mit Bild' => 1, 'Bilder' => 3]);
});

it('changes nothing in a dry run', function () {
    $images = new WooSnapshotImages;
    $images->run(snapshotWithImages(), true);

    expect(DB::table('product_images')->count())->toBe(1)
        ->and($images->counts['Bilder'])->toBe(3);
});
