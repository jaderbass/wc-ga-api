<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make product SKU nullable for variable parent products
 *
 * Hintergrund:
 * - In WooCommerce sollen bei "variable" Hauptprodukten i. d. R. **keine** SKUs gesetzt sein,
 *   da die SKUs auf Variantenebene eindeutig sein müssen.
 * - Diese Migration erlaubt NULL in products.sku und setzt bei bestehenden Parent-Produkten
 *   (product_type='variable') die SKU auf NULL.
 *
 * Hinweise:
 * - MySQL erlaubt mehrere NULL-Werte trotz UNIQUE-Index.
 * - Falls die Column-Änderung "change()" fehlschlägt, ggf. doctrine/dbal installieren:
 *     composer require doctrine/dbal --dev
 *
 * Up-Schritte:
 *  1) Spalte sku auf nullable ändern
 *  2) (Sicherheit) vorhandene Parent-SKUs bei variable-Produkten auf NULL setzen
 *  3) UNIQUE-Index auf sku sicherstellen (mehrfache NULLs sind erlaubt)
 *
 * Down-Schritte:
 *  - sku wieder NOT NULL
 *  - UNIQUE-Index bleibt bestehen
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('products', function (Blueprint $table) {
      // 1) SKU nullable machen
      $table->string('sku', 191)->nullable()->change();
    });

    // 2) Bestehende Parent-SKUs (variable) auf NULL setzen
    DB::table('products')
      ->where('product_type', 'variable')
      ->whereNotNull('sku')
      ->update(['sku' => null]);

    // 3) UNIQUE-Index sicherstellen (Name kann je nach Historie abweichen)
    // Erst versuchen, ggf. vorhandenen UNIQUE neu zu setzen
    // Schema::table('products', function (Blueprint $table) {
      // Falls der Unique-Index fehlt, neu erstellen.
      // Hinweis: Wenn bereits "products_sku_unique" existiert, wirft unique() keinen Fehler,
      // aber zur Sicherheit könntest du vorher dropUnique(['sku']) aufrufen, wenn nötig.
      // $table->dropUnique('products_sku_unique');
      // $table->unique('sku', 'products_sku_unique');
    // });
  }

  public function down(): void
  {
    // Achtung: Down macht sku wieder NOT NULL.
    // Produkte, deren sku aktuell NULL ist, müssen vorher manuell befüllt werden,
    // sonst schlägt die Migration fehl. Daher hier ein konservativer Fallback:
    DB::table('products')
      ->whereNull('sku')
      ->update(['sku' => DB::raw("CONCAT('SKU-', id)")]);

    Schema::table('products', function (Blueprint $table) {
      $table->string('sku', 191)->nullable(false)->change();
    });

    // UNIQUE belassen (optional könntest du ihn hier auch droppen/re-createn)
    // Schema::table('products', function (Blueprint $table) {
    //     $table->dropUnique('products_sku_unique');
    // });
  }
};
