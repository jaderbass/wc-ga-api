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
        Schema::create('petzl_description_sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('batch_id')->nullable()->index();
            $table->uuid('import_run_id')->nullable()->index();

            $table->string('trigger', 20);
            $table->string('mode', 20);
            $table->string('status', 20)->default('queued');

            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petzl_description_sync_runs');
    }
};
