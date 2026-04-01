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
        Schema::create('category_rules', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('keyword');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('keyword');
            $table->unique(['category_id', 'keyword']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
