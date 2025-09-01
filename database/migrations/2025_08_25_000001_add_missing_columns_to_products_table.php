<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddMissingColumnsToProductsTable
 *
 * Fügt in der products-Tabelle neue, bislang fehlende Spalten hinzu.
 *
 * Quelle: Änderungs-Tabelle (Excel) mit den Spalten:
 *  - "neu anlegen": external_url, declaration_of_compliance, manual_url, size,
 *                   certification, author_name, author_mail, author_firstname, author_lastname
 *
 * Annahmen zu Datentypen:
 *  - URLs und Zertifikats-/Dokument-Referenzen als string (bis 2048 für URLs).
 *  - "size" als string (freie Variante/Größenangabe).
 *  - Autor*innen-Felder als string; author_mail zusätzlich indexierbar für spätere Suchen.
 *
 * Down-Migration entfernt ausschließlich die hier neu angelegten Spalten.
 */
return new class extends Migration
{
  /**
   * Führe die Migration aus: Spalten werden hinzugefügt.
   *
   * @return void
   */
  public function up(): void
  {
    Schema::table('products', function (Blueprint $table) {
      // Externe URL zum Produkt (Shop/Hersteller/Landingpage)
      // 2048 Zeichen als konservative Obergrenze für URLs.
      if (!Schema::hasColumn('products', 'external_url')) {
        $table->string('external_url', 2048)->nullable()->after('slug');
      }

      // Konformitätserklärung (z. B. Dateipfad, Nummer, URL)
      if (!Schema::hasColumn('products', 'declaration_of_compliance')) {
        $table->string('declaration_of_compliance', 512)->nullable()->after('external_url');
      }

      // Handbuch / Manual (z. B. URL)
      if (!Schema::hasColumn('products', 'manual_url')) {
        $table->string('manual_url', 2048)->nullable()->after('declaration_of_compliance');
      }

      // Größe (freie Texteingabe / Variantensummary)
      if (!Schema::hasColumn('products', 'size')) {
        $table->string('size', 128)->nullable()->after('short_description');
        $table->index('size', 'products_size_idx');
      }

      // Zertifizierung (z. B. EN-, UIAA-, CE-Angaben als komprimierter String)
      if (!Schema::hasColumn('products', 'certification')) {
        $table->string('certification', 255)->nullable()->after('size');
      }

      // Autor*innen-Informationen (z. B. für redaktionelle Inhalte)
      if (!Schema::hasColumn('products', 'author_firstname')) {
        $table->string('author_firstname', 100)->nullable()->after('certification');
      }

      if (!Schema::hasColumn('products', 'author_lastname')) {
        $table->string('author_lastname', 100)->nullable()->after('author_firstname');
      }

      if (!Schema::hasColumn('products', 'author_name')) {
        // Redundanzfeld (voller Name), hilfreich für einfache Ausgabe/Sortierung
        $table->string('author_name', 200)->nullable()->after('author_lastname');
        $table->index('author_name', 'products_author_name_idx');
      }

      if (!Schema::hasColumn('products', 'author_mail')) {
        $table->string('author_mail', 255)->nullable()->after('author_name');
        $table->index('author_mail', 'products_author_mail_idx');
      }
    });
  }

  /**
   * Rolle rückwärts: Alle hier neu angelegten Spalten wieder entfernen.
   *
   * @return void
   */
  public function down(): void
  {
    Schema::table('products', function (Blueprint $table) {
      // Indizes werden von Blueprint beim Drop der Spalte mit entfernt.
      $dropColumns = [
        'external_url',
        'declaration_of_compliance',
        'manual_url',
        'size',
        'certification',
        'author_firstname',
        'author_lastname',
        'author_name',
        'author_mail',
      ];

      foreach ($dropColumns as $col) {
        if (Schema::hasColumn('products', $col)) {
          $table->dropColumn($col);
        }
      }
    });
  }
};
