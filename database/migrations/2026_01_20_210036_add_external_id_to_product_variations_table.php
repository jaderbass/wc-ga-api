<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table
                ->string('external_id', 64)
                ->nullable()
                ->after('product_id');

            $table->unique('external_id', 'product_variations_external_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropUnique('product_variations_external_id_unique');
            $table->dropColumn('external_id');
        });
    }
};
