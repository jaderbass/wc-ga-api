<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Altes Feld „Herstellerpreis“ entfernen. Listenpreis und EK stehen seit den
 * Preislisten in list_price_cents / purchase_price_cents. Der Altwert war nicht
 * einheitlich (Skylotec = Listenpreis, Aliens = Lieferanten-VK) und wird daher
 * nicht übernommen.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variations'] as $table) {
            if (Schema::hasColumn($table, 'manufacturer_price_cents')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('manufacturer_price_cents');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'product_variations'] as $table) {
            if (! Schema::hasColumn($table, 'manufacturer_price_cents')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('manufacturer_price_cents')->nullable();
                });
            }
        }
    }
};
