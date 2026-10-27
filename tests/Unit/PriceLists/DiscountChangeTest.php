<?php

use App\Models\ManufacturerDiscountChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    try {
        foreach (['manufacturer_discount_changes', 'product_variations', 'products', 'manufacturers'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('manufacturers', function ($t) {
            $t->id();
            $t->string('manufacturer');
            $t->decimal('purchase_discount_1', 5, 2)->nullable();
            $t->decimal('purchase_discount_2', 5, 2)->nullable();
            $t->timestamps();
        });

        foreach (['products', 'product_variations'] as $name) {
            Schema::create($name, function ($t) use ($name) {
                $t->id();
                $name === 'products' ? $t->unsignedBigInteger('manufacturer_id')->nullable() : $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedInteger('list_price_cents')->nullable();
                $t->decimal('purchase_discount_1', 5, 2)->nullable();
                $t->decimal('purchase_discount_2', 5, 2)->nullable();
                $t->unsignedInteger('purchase_price_cents')->nullable();
                $t->string('purchase_price_source', 20)->nullable();
                $t->timestamps();
            });
        }

        (require base_path('database/migrations/2026_10_27_120000_create_manufacturer_discount_changes_table.php'))->up();
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    DB::table('manufacturers')->insert(['id' => 3, 'manufacturer' => 'Petzl', 'purchase_discount_1' => 35, 'purchase_discount_2' => 4]);
    DB::table('products')->insert(['id' => 1, 'manufacturer_id' => 3]);
    // 73,50 € × 0,65 × 0,96 = 45,86 €
    DB::table('product_variations')->insert(['id' => 1, 'product_id' => 1, 'list_price_cents' => 7350, 'purchase_price_cents' => 4586, 'purchase_price_source' => 'calculated']);
});

afterEach(fn () => Carbon::setTestNow());

it('switches the discount on the due date and recalculates purchase prices', function () {
    $this->artisan('prices:schedule-discount', ['manufacturer' => 'petzl', 'discount1' => 35, 'discount2' => 5, '--from' => '2027-01-01'])->assertSuccessful();

    Carbon::setTestNow('2026-12-31 23:00:00');
    $this->artisan('prices:apply-discount-changes')->expectsOutput('Keine fälligen Rabattwechsel.')->assertSuccessful();
    expect((float) DB::table('manufacturers')->value('purchase_discount_2'))->toBe(4.0);

    Carbon::setTestNow('2027-01-01 00:15:00');
    $this->artisan('prices:apply-discount-changes')->assertSuccessful();

    // 73,50 € × 0,65 × 0,95 = 45,39 €
    expect((float) DB::table('manufacturers')->value('purchase_discount_2'))->toBe(5.0)
        ->and(DB::table('product_variations')->value('purchase_price_cents'))->toBe(4539)
        ->and(ManufacturerDiscountChange::query()->first()->applied_at)->not->toBeNull();

    // läuft nicht doppelt
    $this->artisan('prices:apply-discount-changes')->expectsOutput('Keine fälligen Rabattwechsel.');
});
