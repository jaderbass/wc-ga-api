<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('woo_product_id')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('regular_price')->nullable();
            $table->string('sale_price')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->enum('product_type', ['simple', 'variable'])->default('simple');
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'publish'])->default('draft');
            $table->string('slug')->unique();
            $table->timestamp('woo_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
