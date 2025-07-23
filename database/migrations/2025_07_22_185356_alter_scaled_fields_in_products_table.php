<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('width')->nullable()->change();
            $table->integer('length')->nullable()->change();
            $table->integer('height')->nullable()->change();
            $table->integer('boxwidth')->nullable()->change();
            $table->integer('boxlength')->nullable()->change();
            $table->integer('boxheight')->nullable()->change();
            $table->integer('weight')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->smallInteger('width')->nullable()->change();
            $table->smallInteger('length')->nullable()->change();
            $table->smallInteger('height')->nullable()->change();
            $table->smallInteger('boxwidth')->nullable()->change();
            $table->smallInteger('boxlength')->nullable()->change();
            $table->smallInteger('boxheight')->nullable()->change();
            $table->smallInteger('weight')->nullable()->change();
        });
    }
};
