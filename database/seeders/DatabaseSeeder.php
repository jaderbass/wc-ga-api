<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Führt die Datenbank-Seeder aus.
     *
     * Ruft die Seeder für Rollen, Admin-Benutzer und Hersteller in der
     * korrekten Reihenfolge auf.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(ManufacturerSeeder::class);
    }
}
