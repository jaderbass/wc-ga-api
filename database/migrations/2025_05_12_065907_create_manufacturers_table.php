<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer')->unique();        // Name des Herstellers
            $table->string('manufacturercountry', 3)->nullable(); // ISO-3 Ländercode
            $table->string('website')->nullable();
            $table->string('api_url')->nullable();
            $table->string('api_user')->nullable();
            $table->string('api_password')->nullable();      // verschlüsselt gespeichert
            $table->string('api_token')->nullable();         // z. B. für Token-basierte APIs
            $table->enum('import_type', ['csv', 'xml', 'api'])->default('csv'); // Art des Imports
            $table->text('notes')->nullable();               // Freitextnotizen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
    }
};
