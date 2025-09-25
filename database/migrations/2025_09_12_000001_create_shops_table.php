<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('base_url');
            $table->string('api_version')->default('wc/v3');
            $table->string('consumer_key');
            $table->string('consumer_secret');
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->json('rate_limit_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['base_url', 'api_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
