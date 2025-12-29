<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('manufacturer_id');
            $table->string('source_type', 20);
            $table->text('source');
            $table->unsignedBigInteger('author_id')->nullable();

            $table->string('status', 20)->default('queued'); // queued|running|done|failed
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['manufacturer_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
