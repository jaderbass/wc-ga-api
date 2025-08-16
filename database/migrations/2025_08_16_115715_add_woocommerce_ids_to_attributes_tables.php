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
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->bigInteger('woo_attribute_id')->nullable()->after('id');
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->bigInteger('woo_term_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropColumn('woo_attribute_id');
        });

        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropColumn('woo_term_id');
        });
    }
};
