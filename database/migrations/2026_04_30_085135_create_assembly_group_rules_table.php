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
        Schema::create('assembly_group_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('field'); // erstmal nur 'product_name'
            $table->string('operator'); // 'contains'
            $table->string('value');
            $table->unsignedInteger('assembly_group');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_group_rules');
    }
};
