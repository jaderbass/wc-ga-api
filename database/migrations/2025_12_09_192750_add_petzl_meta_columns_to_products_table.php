<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Zusätzliche Metadaten aus Petzl (und ggf. anderen Herstellern)

            // Nach der Beschreibung einordnen, falls vorhanden
            $table->string('designation')
                ->nullable()
                ->after('description');

            $table->string('type')
                ->nullable()
                ->after('designation');

            $table->string('category')
                ->nullable()
                ->after('type');

            $table->string('subcategory')
                ->nullable()
                ->after('category');

            $table->string('market')
                ->nullable()
                ->after('subcategory');

            $table->string('customs')
                ->nullable()
                ->after('market');

            // ISO-Ländercode o.ä. (RO, FR, DE ...)
            $table->string('made_in', 8)
                ->nullable()
                ->after('customs');

            // Materialien können länger werden → text
            // certification-Spalte existiert ja schon, daher danach anhängen
            $table->text('materials')
                ->nullable()
                ->after('certification');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'designation',
                'type',
                'category',
                'subcategory',
                'market',
                'customs',
                'made_in',
                'materials',
            ]);
        });
    }
};
