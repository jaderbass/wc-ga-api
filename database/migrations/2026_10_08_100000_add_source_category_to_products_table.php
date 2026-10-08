<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Herstellerkategorie (roh, wie in der Herstellerliste) am Produkt.
 *
 * Herstellerunabhängig – ersetzt nicht die Petzl-Spalten, sondern wird
 * für Petzl einmalig aus ihnen befüllt ("Kategorie > Unterkategorie").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source_category')->nullable()->index();
        });

        if (! Schema::hasColumn('products', 'petzl_source_category')) {
            return;
        }

        DB::table('products')
            ->whereNotNull('petzl_source_category')
            ->where('petzl_source_category', '!=', '')
            ->orderBy('id')
            ->select(['id', 'petzl_source_category', 'petzl_source_subcategory'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $value = trim((string) $row->petzl_source_category);
                    $sub = trim((string) $row->petzl_source_subcategory);

                    if ($sub !== '') {
                        $value .= ' > '.$sub;
                    }

                    DB::table('products')->where('id', $row->id)->update(['source_category' => $value]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['source_category']);
            $table->dropColumn('source_category');
        });
    }
};
