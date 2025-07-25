<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->string('api_user')->nullable();
            $table->string('api_password')->nullable(); // verschlüsselt gespeichert
        });
    }

    public function down(): void
    {
        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropColumn(['api_user', 'api_password']);
        });
    }
};
