<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('category_product', function (Blueprint $table): void {
            $table->string('assignment_type')
                ->default('auto')
                ->after('product_id');
        });

        DB::table('category_product')
            ->whereNull('assignment_type')
            ->update(['assignment_type' => 'auto']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('category_product', function (Blueprint $table): void {
            $table->dropColumn('assignment_type');
        });
    }
};
