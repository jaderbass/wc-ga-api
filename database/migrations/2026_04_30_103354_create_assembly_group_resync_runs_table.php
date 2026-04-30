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
        Schema::create('assembly_group_resync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('queued'); // queued, running, finished, failed
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assembly_group_resync_runs');
    }
};
