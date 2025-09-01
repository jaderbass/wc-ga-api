<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class RemoveUnusedColumnsFromProductsTable
 *
 * Entfernt überflüssige Spalten aus der products-Tabelle gemäß Änderungs-Excel.
 *
 * Entfernen laut Excel-Spalte „entfernen“:
 *  - regular_price
 *  - sale_price
 *  - stock_quantity
 *  - status
 *  - woo_synced_at
 *  - price
 *  - unit_price
 *  - pcs_per_box
 *  - mpn
 *
 * Hinweis:
 *  Die Down-Migration stellt die Spalten mit üblichen, konservativen Datentypen
 *  (nullable, sinnvolle Längen/Präzision) wieder her. Falls deine vorherigen
 *  Typen/Defaults abweichen, sag Bescheid – ich passe das in einer separaten
 *  Migration gerne exakt an.
 */
return new class extends Migration
{
  /**
   * Führe die Migration aus: markierte Spalten werden entfernt.
   *
   * @return void
   */
  public function up(): void
  {
    Schema::table('products', function (Blueprint $table) {
      $dropCols = [
        'regular_price',
        'sale_price',
        'stock_quantity',
        'status',
        'woo_synced_at',
        'price',
        'unit_price',
        'pcs_per_box',
        'mpn',
      ];

      foreach ($dropCols as $col) {
        if (Schema::hasColumn('products', $col)) {
          $table->dropColumn($col);
        }
      }
    });
  }

  /**
   * Rolle rückwärts: entfernte Spalten wieder hinzufügen (konservative Typen).
   *
   * @return void
   */
  public function down(): void
  {
    Schema::table('products', function (Blueprint $table) {
      // Preise als DECIMAL(10,2), nullable
      if (!Schema::hasColumn('products', 'regular_price')) {
        $table->decimal('regular_price', 10, 2)->nullable()->after('short_description');
      }
      if (!Schema::hasColumn('products', 'sale_price')) {
        $table->decimal('sale_price', 10, 2)->nullable()->after('regular_price');
      }
      if (!Schema::hasColumn('products', 'price')) {
        $table->decimal('price', 10, 2)->nullable()->after('sale_price');
      }
      if (!Schema::hasColumn('products', 'unit_price')) {
        $table->decimal('unit_price', 10, 2)->nullable()->after('price');
      }

      // Bestand als unsigned Integer (konservativ), Default 0
      if (!Schema::hasColumn('products', 'stock_quantity')) {
        $table->unsignedInteger('stock_quantity')->default(0)->after('stock_status');
      }

      // Status als kurzer String
      if (!Schema::hasColumn('products', 'status')) {
        $table->string('status', 50)->nullable()->after('product_type');
      }

      // WooCommerce Sync-Zeitpunkt
      if (!Schema::hasColumn('products', 'woo_synced_at')) {
        $table->timestamp('woo_synced_at')->nullable()->after('status');
      }

      // Verpackungseinheit
      if (!Schema::hasColumn('products', 'pcs_per_box')) {
        $table->unsignedInteger('pcs_per_box')->nullable()->after('unit');
      }

      // Herstellernummer (MPN)
      if (!Schema::hasColumn('products', 'mpn')) {
        $table->string('mpn', 100)->nullable()->after('ean');
      }
    });
  }
};
