<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_variations', 'slug')) {
                $table->string('slug')->nullable()->after('sku');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table): void {
            if (Schema::hasColumn('product_variations', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }
};
