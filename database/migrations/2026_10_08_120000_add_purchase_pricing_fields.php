<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einkaufspreise: Listenpreis + zwei Rabattstufen je Hersteller → EK.
 *
 * - manufacturers.purchase_discount_1 / _2: Rabatt in Prozent, nacheinander
 *   angewendet (Petzl: 35 %, dann 5 % vom bereits rabattierten Preis)
 * - list_price_cents: Listenpreis netto laut Preisliste
 * - purchase_price_cents: EK netto
 * - purchase_price_source: "calculated" (aus Listenpreis und Rabatten)
 *   oder "pricelist" (EK direkt aus der Liste, z. B. Aliens HEK)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->decimal('purchase_discount_1', 5, 2)->nullable();
            $table->decimal('purchase_discount_2', 5, 2)->nullable();
        });

        foreach (['products', 'product_variations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('list_price_cents')->nullable();
                $table->unsignedInteger('purchase_price_cents')->nullable();
                $table->string('purchase_price_source', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'product_variations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['list_price_cents', 'purchase_price_cents', 'purchase_price_source']);
            });
        }

        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropColumn(['purchase_discount_1', 'purchase_discount_2']);
        });
    }
};
