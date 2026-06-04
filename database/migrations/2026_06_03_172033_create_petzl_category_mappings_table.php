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
        Schema::create('petzl_category_mappings', function (Blueprint $table) {
            $table->id();

            $table->string('source_category');
            $table->string('source_subcategory')->nullable();

            $table->string('translated_category')->nullable();
            $table->string('translated_subcategory')->nullable();

            $table->string('petzl_path')->nullable();

            $table->boolean('is_reviewed')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'source_category',
                'source_subcategory',
            ], 'petzl_category_mappings_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petzl_category_mappings');
    }
};
