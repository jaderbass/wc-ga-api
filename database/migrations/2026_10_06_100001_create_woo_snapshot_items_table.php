<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woo_snapshot_id')->constrained('woo_snapshots')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_id');
            $table->unsignedBigInteger('woo_parent_id')->nullable();
            $table->string('type', 20);
            $table->string('status', 20)->nullable();
            $table->string('sku', 191)->nullable();
            $table->string('sku_key', 191)->nullable();
            $table->string('ean', 64)->nullable();
            $table->string('ean_key', 32)->nullable();
            $table->string('name', 500)->nullable();
            $table->string('regular_price', 32)->nullable();
            $table->longText('payload')->nullable();
            $table->string('match_status', 20)->nullable();
            $table->string('match_method', 20)->nullable();
            $table->unsignedBigInteger('matched_product_id')->nullable();
            $table->unsignedBigInteger('matched_variation_id')->nullable();
            $table->unsignedSmallInteger('candidate_count')->default(0);
            $table->timestamps();

            $table->index(['woo_snapshot_id', 'type']);
            $table->index(['woo_snapshot_id', 'match_status']);
            $table->index(['woo_snapshot_id', 'sku_key']);
            $table->index(['woo_snapshot_id', 'matched_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_snapshot_items');
    }
};
