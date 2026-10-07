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
        Schema::table('products', function (Blueprint $table) {
            $table->longText('petzl_description_html')->nullable()->after('description');
            $table->string('petzl_description_source_url')->nullable()->after('petzl_description_html');
            $table->timestamp('petzl_description_fetched_at')->nullable()->after('petzl_description_source_url');
            $table->string('petzl_description_hash')->nullable()->after('petzl_description_fetched_at');
            $table->string('description_source')->default('auto')->after('petzl_description_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'petzl_description_html',
                'petzl_description_source_url',
                'petzl_description_fetched_at',
                'petzl_description_hash',
                'description_source',
            ]);
        });
    }
};
