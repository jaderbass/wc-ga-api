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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Identifikation
            $table->string('productnumber')->unique();
            $table->string('productname')->nullable();
            $table->text('description')->nullable();
            $table->text('shortdescription')->nullable();
            $table->string('eancode')->nullable();
            $table->string('skucode')->nullable();

            // Preise (in Cent, also z. B. 1999 für 19,99 €)
            $table->integer('price')->nullable();
            $table->integer('regularprice')->nullable();
            $table->integer('saleprice')->nullable();
            $table->integer('unitprice')->nullable();

            // Maße (in Millimeter × 100, z. B. 1234 für 12,34 cm)
            $table->integer('width')->nullable();
            $table->integer('length')->nullable();
            $table->integer('height')->nullable();
            $table->integer('boxwidth')->nullable();
            $table->integer('boxlength')->nullable();
            $table->integer('boxheight')->nullable();

            // Gewicht in Gramm (ggf. ×100)
            $table->integer('weight')->nullable();

            // Weitere Infos
            $table->string('unit')->nullable(); // z. B. "Stück", "kg", etc.
            $table->integer('pcsperbox')->nullable();
            $table->string('manufacturercountry')->nullable();

            // Beziehung zum Hersteller
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
