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
            // Alte, nicht mehr genutzte Author-Felder entfernen
            if (Schema::hasColumn('products', 'author_firstname')) {
                $table->dropColumn('author_firstname');
            }
            if (Schema::hasColumn('products', 'author_lastname')) {
                $table->dropColumn('author_lastname');
            }
            if (Schema::hasColumn('products', 'author_name')) {
                $table->dropColumn('author_name');
            }
            if (Schema::hasColumn('products', 'author_mail')) {
                $table->dropColumn('author_mail');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Felder wiederherstellen (falls Rollback)
            $table->string('author_firstname', 100)->nullable();
            $table->string('author_lastname', 100)->nullable();
            $table->string('author_name', 255)->nullable();
            $table->string('author_mail', 255)->nullable();
        });
    }
};
