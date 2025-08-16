<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // IDs
            $table->id();

            // Foreign-Keys
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();

            // WooCommerce Informations
            $table->string('slug')->unique();
            $table->bigInteger('woo_product_id')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->enum('product_type', ['simple', 'variable'])->default('simple');
            $table->enum('status', ['draft', 'publish'])->default('draft');
            $table->timestamp('woo_synced_at')->nullable();

            // Product numbers
            $table->string('sku')->nullable()->unique();
            $table->string('ean')->nullable();
            $table->string('mpn')->nullable();
            $table->string('product_number')->nullable();

            // designations
            $table->string('product_name')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();

            // prices
            $table->string('price')->nullable();
            $table->string('regular_price')->nullable();
            $table->string('sale_price')->nullable();
            $table->string('unit_price')->nullable();

            // meta informations
            $table->string('width')->nullable();       // Maße als Text (z.B. "25 cm")
            $table->string('length')->nullable();
            $table->string('height')->nullable();
            $table->string('unit')->nullable();
            $table->string('pcs_per_box')->nullable();
            $table->string('box_width')->nullable();
            $table->string('box_length')->nullable();
            $table->string('box_height')->nullable();
            $table->string('weight')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
