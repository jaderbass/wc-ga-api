<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bezeichnung einer Kategorie im Produktnamen.
 *
 * Leer = Kategoriename. Beispiel: Kategorie "Statisch" (unter Seile)
 * → im Namen "Statikseil": "TENDON - Statikseil - Canyon Dry - 9,0 mm".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_label')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('name_label');
        });
    }
};
