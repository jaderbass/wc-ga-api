<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hersteller-Zuordnung: Herstellerkategorie bzw. Stichwort → Shop-Kategorien.
 *
 * - nur source_category      = Zuordnung einer Herstellerkategorie
 * - keyword (+ optional source_category) = Stichwort-Ausnahme
 * - manufacturer_id null     = gilt für alle Hersteller
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_category')->nullable();
            $table->string('keyword')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('exclude')->default(false);
            $table->boolean('is_reviewed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['manufacturer_id', 'source_category'], 'car_manufacturer_source_index');
        });

        Schema::create('category_category_assignment_rule', function (Blueprint $table) {
            $table->foreignId('category_assignment_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->primary(['category_assignment_rule_id', 'category_id'], 'ccar_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_category_assignment_rule');
        Schema::dropIfExists('category_assignment_rules');
    }
};
