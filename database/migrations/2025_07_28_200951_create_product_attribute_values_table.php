<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Führt die Migration aus und erstellt die `product_attribute_values` Tabelle.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
            $table->string('value');
            $table->string('slug');
        $table->unsignedBigInteger('woo_term_id')->nullable();
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });
    }

    /**
     * Macht die Migration rückgängig und löscht die `product_attribute_values` Tabelle.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
