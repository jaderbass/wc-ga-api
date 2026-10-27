<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geplante Rabattwechsel je Hersteller (z. B. Petzl ab 01.01.2027 wieder 35 % + 5 %).
 * Am Stichtag übernimmt prices:apply-discount-changes die Werte und rechnet die EK neu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturer_discount_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained()->cascadeOnDelete();
            $table->decimal('purchase_discount_1', 5, 2)->nullable();
            $table->decimal('purchase_discount_2', 5, 2)->nullable();
            $table->date('valid_from');
            $table->timestamp('applied_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['valid_from', 'applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturer_discount_changes');
    }
};
