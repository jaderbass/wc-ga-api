<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_woo_links_table
 *
 * Zweck:
 * - Persistente Zuordnung zwischen lokaler SKU und WooCommerce-Entitäten (Parent/Variation).
 * - Bewahrt sowohl lokale SKU (local_sku) als auch die tatsächliche Woo-SKU (woo_sku),
 *   da Woo-SKU historisch MPN o.Ä. sein kann und NICHT geändert werden darf.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('woo_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('shop_id');

            // SKUs getrennt führen (normalisiert; Pflege erfolgt in Code):
            $table->string('local_sku', 191)->nullable();
            $table->string('woo_sku', 191)->nullable();

            $table->unsignedBigInteger('woo_product_id')->nullable();   // Parent-ID
            $table->unsignedBigInteger('woo_variation_id')->nullable(); // Variation-ID

            $table->string('ean', 32)->nullable();
            $table->string('mpn', 191)->nullable();

            $table->boolean('protect_sku')->default(true); // NIE SKU in Woo ändern
            $table->unsignedTinyInteger('confidence')->default(100);

            $table->timestamps();

            // Eindeutigkeit: Woo-SKU ist in Woo der maßgebliche Schlüssel
            $table->unique(['shop_id', 'woo_sku'], 'uniq_shop_woo_sku');

            // Sekundärindizes
            $table->index(['shop_id', 'local_sku'], 'idx_shop_local_sku');
            $table->index(['shop_id', 'woo_product_id'], 'idx_shop_product');
            $table->index(['shop_id', 'woo_variation_id'], 'idx_shop_variation');
            $table->index(['shop_id', 'ean'], 'idx_shop_ean');
            $table->index(['shop_id', 'mpn'], 'idx_shop_mpn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woo_links');
    }
};
