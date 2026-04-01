<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace legacy german column "baugruppe" with english "assembly_group".
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('assembly_group')
                ->default(1)
                ->after('product_name')
                ->index();
        });

        if (Schema::hasColumn('products', 'baugruppe')) {
            DB::table('products')->update([
                'assembly_group' => DB::raw('COALESCE(baugruppe, 1)'),
            ]);

            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('baugruppe');
            });
        }
    }

    /**
     * Restore legacy german column "baugruppe" if rolled back.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('baugruppe')
                ->nullable()
                ->index();
        });

        DB::table('products')->update([
            'baugruppe' => DB::raw('assembly_group'),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('assembly_group');
        });
    }
};
