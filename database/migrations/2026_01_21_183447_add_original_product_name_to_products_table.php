<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `original_product_name` to store the raw manufacturer designation
 * as provided by the supplier feed/CSV.
 *
 * Generated Woo product name is stored in `products.product_name`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table
                ->string('original_product_name', 255)
                ->nullable()
                ->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('original_product_name');
        });
    }
};
