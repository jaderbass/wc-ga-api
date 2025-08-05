<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder für einen Standard-Admin-Benutzer.
 *
 * Erstellt einen Admin-Benutzer basierend auf .env-Werten,
 * wenn noch keiner mit dieser E-Mail existiert.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Führt den Seeder aus.
     *
     * @return void
     */
    public function run(): void
    {
        // Werte aus .env laden
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password123');
        $name = env('ADMIN_NAME', 'Administrator');

        // Sicherstellen, dass die Rolle Admin existiert
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        // Prüfen, ob Benutzer bereits existiert
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        // Rolle zuweisen
        if (! $user->hasRole('Admin')) {
            $user->assignRole($adminRole);
        }
    }
}
