<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variation_attribute_value', function (Blueprint $table) {
            // Foreign key für die Produktvariante mit benutzerdefiniertem, kurzem Indexnamen
            $table->foreignId('product_variation_id')
                  ->constrained(
                      table: 'product_variations',
                      indexName: 'piv_var_attr_variation_id_foreign'
                  )->onDelete('cascade');

            // Foreign key für den Attributwert mit einem benutzerdefinierten, kürzeren Indexnamen
            $table->foreignId('product_attribute_value_id')
                  ->constrained(
                      table: 'product_attribute_values',
                      indexName: 'piv_var_attr_value_id_foreign'
                  )->onDelete('cascade');

            // Setzt einen zusammengesetzten Primärschlüssel, um Duplikate zu verhindern
            // Auch hier wird ein kürzerer, benutzerdefinierter Name vergeben.
            $table->primary(
                ['product_variation_id', 'product_attribute_value_id'],
                'piv_var_attr_primary'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variation_attribute_value');
    }
};
