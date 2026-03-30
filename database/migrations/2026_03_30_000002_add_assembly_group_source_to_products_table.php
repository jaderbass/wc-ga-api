<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add source tracking for assembly group values.
     *
     * auto   = automatisch aus Produktnamen abgeleitet
     * manual = manuell im UI gesetzt
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('assembly_group_source', 16)
                ->default('auto')
                ->after('assembly_group')
                ->index();
        });

        DB::table('products')
            ->whereNull('assembly_group_source')
            ->update([
                'assembly_group_source' => 'auto',
            ]);
    }

    /**
     * Remove source tracking for assembly group values.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('assembly_group_source');
        });
    }
};
