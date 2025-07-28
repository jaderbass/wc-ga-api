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
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('woo_variation_id')->nullable();
            $table->string('sku')->unique();
            $table->string('regular_price')->nullable();
            $table->string('sale_price')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->json('attributes')->nullable(); // JSON: {"size":"M","color":"Blue"}
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
