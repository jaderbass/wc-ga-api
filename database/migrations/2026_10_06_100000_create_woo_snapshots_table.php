<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('products_count')->default(0);
            $table->unsignedInteger('variations_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_snapshots');
    }
};
