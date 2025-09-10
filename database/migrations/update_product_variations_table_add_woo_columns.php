<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('product_variations', function (Blueprint $table) {
      // Identifiers
      if (!Schema::hasColumn('product_variations', 'sku')) {
        $table->string('sku', 128)->nullable()->index();
      }
      if (!Schema::hasColumn('product_variations', 'ean')) {
        $table->string('ean', 32)->nullable()->index();
      }

      // Pricing (Cent)
      if (!Schema::hasColumn('product_variations', 'regular_price_cents')) {
        $table->bigInteger('regular_price_cents')->default(0);
      }
      if (!Schema::hasColumn('product_variations', 'sale_price_cents')) {
        $table->bigInteger('sale_price_cents')->default(0);
      }

      // Dimensions & Weight (Integer)
      if (!Schema::hasColumn('product_variations', 'weight_g')) {
        $table->integer('weight_g')->default(0);
      }
      if (!Schema::hasColumn('product_variations', 'length_mm')) {
        $table->integer('length_mm')->default(0);
      }
      if (!Schema::hasColumn('product_variations', 'width_mm')) {
        $table->integer('width_mm')->default(0);
      }
      if (!Schema::hasColumn('product_variations', 'height_mm')) {
        $table->integer('height_mm')->default(0);
      }

      // Inventory / Stock
      if (!Schema::hasColumn('product_variations', 'stock_quantity')) {
        $table->integer('stock_quantity')->default(0);
      }
      if (!Schema::hasColumn('product_variations', 'manage_stock')) {
        $table->boolean('manage_stock')->default(false);
      }
      if (!Schema::hasColumn('product_variations', 'backorders')) {
        $table->enum('backorders', ['no', 'notify', 'yes'])->default('no');
      }
      if (!Schema::hasColumn('product_variations', 'stock_status')) {
        $table->enum('stock_status', ['instock', 'outofstock', 'onbackorder'])->default('instock');
      }

      // Attribute-Matrix (aus euren Mehrfachspalten)
      if (!Schema::hasColumn('product_variations', 'attributes_json')) {
        $table->json('attributes_json')->nullable();
      }
    });
  }

  public function down(): void
  {
    Schema::table('product_variations', function (Blueprint $table) {
      foreach (
        [
          'sku',
          'ean',
          'regular_price_cents',
          'sale_price_cents',
          'weight_g',
          'length_mm',
          'width_mm',
          'height_mm',
          'stock_quantity',
          'manage_stock',
          'backorders',
          'stock_status',
          'attributes_json',
        ] as $col
      ) {
        if (Schema::hasColumn('product_variations', $col)) {
          $table->dropColumn($col);
        }
      }
    });
  }
};
