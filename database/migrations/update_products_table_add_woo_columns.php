<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('products', function (Blueprint $table) {
      // Identifiers
      if (!Schema::hasColumn('products', 'ean')) {
        $table->string('ean', 32)->nullable()->index(); // EAN/GTIN
      }
      if (!Schema::hasColumn('products', 'mpn')) {
        $table->string('mpn', 64)->nullable()->index();
      }

      // Pricing (Cent)
      if (!Schema::hasColumn('products', 'regular_price_cents')) {
        $table->bigInteger('regular_price_cents')->default(0);
      }
      if (!Schema::hasColumn('products', 'sale_price_cents')) {
        $table->bigInteger('sale_price_cents')->default(0);
      }

      // Tax
      if (!Schema::hasColumn('products', 'tax_class')) {
        $table->string('tax_class', 64)->nullable(); // z.B. 'reduced-rate' / 'standard'
      }
      if (!Schema::hasColumn('products', 'tax_status')) {
        // enum in Laravel 9+: passt; sonst als string(16)
        $table->enum('tax_status', ['taxable', 'shipping', 'none'])->default('taxable');
      }

      // Dimensions & Weight (Integer)
      if (!Schema::hasColumn('products', 'weight_g')) {
        $table->integer('weight_g')->default(0);
      }
      if (!Schema::hasColumn('products', 'length_mm')) {
        $table->integer('length_mm')->default(0);
      }
      if (!Schema::hasColumn('products', 'width_mm')) {
        $table->integer('width_mm')->default(0);
      }
      if (!Schema::hasColumn('products', 'height_mm')) {
        $table->integer('height_mm')->default(0);
      }

      // Inventory / Stock
      if (!Schema::hasColumn('products', 'stock_quantity')) {
        $table->integer('stock_quantity')->default(0);
      }
      if (!Schema::hasColumn('products', 'manage_stock')) {
        $table->boolean('manage_stock')->default(false);
      }
      if (!Schema::hasColumn('products', 'backorders')) {
        $table->enum('backorders', ['no', 'notify', 'yes'])->default('no');
      }
      if (!Schema::hasColumn('products', 'stock_status')) {
        $table->enum('stock_status', ['instock', 'outofstock', 'onbackorder'])->default('instock');
      }

      // Shipping
      if (!Schema::hasColumn('products', 'shipping_class')) {
        $table->string('shipping_class', 64)->nullable()->index(); // später Woo-Export → Taxonomie
      }

      // Optional laut Mapping
      if (!Schema::hasColumn('products', 'hs_code')) {
        $table->string('hs_code', 32)->nullable()->index();
      }
      if (!Schema::hasColumn('products', 'country_of_origin')) {
        $table->string('country_of_origin', 2)->nullable()->index(); // ISO-3166-1 alpha-2
      }
    });
  }

  public function down(): void
  {
    Schema::table('products', function (Blueprint $table) {
      foreach (
        [
          'ean',
          'mpn',
          'regular_price_cents',
          'sale_price_cents',
          'tax_class',
          'tax_status',
          'weight_g',
          'length_mm',
          'width_mm',
          'height_mm',
          'stock_quantity',
          'manage_stock',
          'backorders',
          'stock_status',
          'shipping_class',
          'hs_code',
          'country_of_origin',
        ] as $col
      ) {
        if (Schema::hasColumn('products', $col)) {
          $table->dropColumn($col);
        }
      }
    });
  }
};
