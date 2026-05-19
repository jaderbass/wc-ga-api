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
        Schema::create('petzl_translations', function (Blueprint $table) {
            $table->id();

            $table->string('source_column'); // Category / Designation
            $table->text('source_text');
            $table->text('translated_text')->nullable();

            $table->string('source_lang', 10)->default('EN');
            $table->string('target_lang', 10)->default('DE');

            $table->string('provider')->nullable(); // deepl / manual
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'source_column',
                'source_text',
                'source_lang',
                'target_lang',
            ], 'petzl_translation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petzl_translations');
    }
};
