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
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('website')->nullable()->after('manufacturercountry');
            $table->string('api_url')->nullable()->after('website');
            $table->string('api_token')->nullable()->after('api_url');
            $table->string('import_type')->default('csv')->after('api_token');
            $table->text('notes')->nullable()->after('import_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            //
        });
    }
};
