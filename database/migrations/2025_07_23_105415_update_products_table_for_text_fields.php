<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Neue Felder als Text anlegen
            $table->string('width_new')->nullable();
            $table->string('length_new')->nullable();
            $table->string('height_new')->nullable();
            $table->string('boxwidth_new')->nullable();
            $table->string('boxlength_new')->nullable();
            $table->string('boxheight_new')->nullable();
            $table->string('weight_new')->nullable();
            $table->string('unit_new', 50)->nullable();

            $table->string('price_new')->nullable();
            $table->string('regularprice_new')->nullable();
            $table->string('saleprice_new')->nullable();
            $table->string('unitprice_new')->nullable();
        });

        // Optional: hier könnte man Werte migrieren, falls nötig
        // DB::table('products')->update([...]);

        Schema::table('products', function (Blueprint $table) {
            // Alte Felder droppen
            $table->dropColumn([
                'width',
                'length',
                'height',
                'boxwidth',
                'boxlength',
                'boxheight',
                'weight',
                'unit',
                'price',
                'regularprice',
                'saleprice',
                'unitprice'
            ]);

            // Neue Felder auf alte Namen umbenennen
            $table->renameColumn('width_new', 'width');
            $table->renameColumn('length_new', 'length');
            $table->renameColumn('height_new', 'height');
            $table->renameColumn('boxwidth_new', 'boxwidth');
            $table->renameColumn('boxlength_new', 'boxlength');
            $table->renameColumn('boxheight_new', 'boxheight');
            $table->renameColumn('weight_new', 'weight');
            $table->renameColumn('unit_new', 'unit');

            $table->renameColumn('price_new', 'price');
            $table->renameColumn('regularprice_new', 'regularprice');
            $table->renameColumn('saleprice_new', 'saleprice');
            $table->renameColumn('unitprice_new', 'unitprice');
        });
    }

    public function down(): void
    {
        // Umkehrung: wieder Integer/TinyInt anlegen
        Schema::table('products', function (Blueprint $table) {
            $table->integer('width')->nullable();
            $table->integer('length')->nullable();
            $table->integer('height')->nullable();
            $table->integer('boxwidth')->nullable();
            $table->integer('boxlength')->nullable();
            $table->integer('boxheight')->nullable();
            $table->integer('weight')->nullable();
            $table->tinyInteger('unit')->nullable();

            $table->integer('price')->nullable();
            $table->integer('regularprice')->nullable();
            $table->integer('saleprice')->nullable();
            $table->integer('unitprice')->nullable();
        });
    }
};
