<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Rohwert aus dem Hersteller-Feed, z. B. "130 x 76"
            $table->string('dimensions_raw')
                ->nullable()
                ->after('size');

            // Aufgetrennte Maße in Millimetern (optional nutzbar für Versand/Filter)
            $table->unsignedInteger('dimension_length_mm')
                ->nullable()
                ->after('dimensions_raw');

            $table->unsignedInteger('dimension_width_mm')
                ->nullable()
                ->after('dimension_length_mm');

            $table->unsignedInteger('dimension_height_mm')
                ->nullable()
                ->after('dimension_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'dimensions_raw',
                'dimension_length_mm',
                'dimension_width_mm',
                'dimension_height_mm',
            ]);
        });
    }
};
