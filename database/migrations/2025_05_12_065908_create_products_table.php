<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            // Preise (frei als Text, da Quelle unterschiedlich ist: CSV vs XML)
            $table->string('price')->nullable();
            $table->string('regularprice')->nullable();
            $table->string('saleprice')->nullable();
            $table->string('unitprice')->nullable();

            // Maße & Gewicht als Text (z.B. "366 g • 12.9 oz")
            $table->string('width')->nullable();
            $table->string('length')->nullable();
            $table->string('height')->nullable();
            $table->string('boxwidth')->nullable();
            $table->string('boxlength')->nullable();
            $table->string('boxheight')->nullable();
            $table->string('weight')->nullable();

            // Einheit
            $table->string('unit', 50)->nullable();

            // Sonstige Angaben
            $table->integer('pcsperbox')->nullable();
            $table->string('manufacturercountry')->nullable();

            // Beziehung zu Hersteller
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
