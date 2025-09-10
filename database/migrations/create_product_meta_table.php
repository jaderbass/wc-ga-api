<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    if (!Schema::hasTable('product_meta')) {
      Schema::create('product_meta', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id')->index();
        // Optional: Meta für Variationen
        $table->unsignedBigInteger('variation_id')->nullable()->index();
        $table->enum('scope', ['product', 'variation'])->default('product')->index();

        $table->string('key', 191)->index();
        $table->longText('value')->nullable();

        $table->timestamps();

        // FK (weich, um Masseneinfügungen nicht zu bremsen – aktiviere gern hart, wenn du willst)
        // $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        // $table->foreign('variation_id')->references('id')->on('product_variations')->cascadeOnDelete();

        // Eindeutigkeit je Scope
        $table->unique(['product_id', 'variation_id', 'scope', 'key'], 'product_meta_unique_scope_key');
      });
    }
  }

  public function down(): void
  {
    if (Schema::hasTable('product_meta')) {
      Schema::dropIfExists('product_meta');
    }
  }
};
