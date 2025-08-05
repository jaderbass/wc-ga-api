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
            $table->bigInteger('woo_product_id')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('regular_price')->nullable();
            $table->string('sale_price')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])->default('in_stock');
            $table->enum('product_type', ['simple', 'variable'])->default('simple');
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'publish'])->default('draft');
            $table->string('slug')->unique();
            // Zusätzliche Felder für Lieferantenimport
            $table->string('productnumber')->nullable();
            $table->string('eancode')->nullable();
            $table->string('skucode')->nullable();
            $table->string('productname')->nullable();
            $table->string('price')->nullable();
            $table->string('regularprice')->nullable();
            $table->string('saleprice')->nullable();
            $table->string('width')->nullable();       // Maße als Text (z.B. "25 cm")
            $table->string('length')->nullable();
            $table->string('height')->nullable();
            $table->string('unit')->nullable();
            $table->string('unitprice')->nullable();
            $table->string('pcsperbox')->nullable();
            $table->string('boxwidth')->nullable();
            $table->string('boxlength')->nullable();
            $table->string('boxheight')->nullable();
            $table->string('mpn')->nullable();
            $table->string('weight')->nullable();

            $table->timestamp('woo_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
