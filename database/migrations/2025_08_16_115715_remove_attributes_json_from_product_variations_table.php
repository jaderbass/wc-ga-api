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
        // Prüfen, ob die Spalte existiert, bevor sie gelöscht wird.
        if (Schema::hasColumn('product_variations', 'attributes')) {
            Schema::table('product_variations', function (Blueprint $table) {
                $table->dropColumn('attributes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Prüfen, ob die Spalte NICHT existiert, bevor sie hinzugefügt wird.
        if (!Schema::hasColumn('product_variations', 'attributes')) {
            Schema::table('product_variations', function (Blueprint $table) {
                $table->json('attributes')->nullable()->after('stock_status');
            });
        }
    }
};
