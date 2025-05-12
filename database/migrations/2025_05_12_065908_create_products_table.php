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
            $table->string('productnumber', length: 100)->nullable();
            $table->string('eancode', length: 14)->nullable();
            $table->string('skucode', length: 32)->nullable();
            $table->string('productname', length: 100);
            $table->text('description');
            $table->string('shortdescription')->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('regularprice');
            $table->unsignedInteger('saleprice');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('length')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->boolean('hasoptions')->default(false);
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->cascadeOnDelete();
            $table->boolean('unit')->default(false);
            $table->unsignedInteger('unitprice')->nullable();
            $table->unsignedSmallInteger('pcsperbox')->nullable();
            $table->unsignedSmallInteger('boxwidth')->nullable();
            $table->unsignedSmallInteger('boxlength')->nullable();
            $table->unsignedSmallInteger('boxheight')->nullable();
            $table->unsignedSmallInteger('weight')->nullable();
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
