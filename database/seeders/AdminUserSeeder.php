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
     * Führt den Seeder aus, um die initialen Admin-Benutzer anzulegen.
     *
     * Erstellt einen Admin-Benutzer basierend auf den Werten in der .env-Datei
     * sowie einen festen Admin-Benutzer für "Jörg Aderhold".
     * Allen erstellten Benutzern wird die Rolle "Admin" zugewiesen.
     * Verwendet `firstOrCreate`, um doppelte Einträge zu verhindern.
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

        // Zusätzlichen Admin-Benutzer "Jörg Aderhold" anlegen
        $joergUser = User::firstOrCreate(
            ['email' => 'joerg@jaderbass.de'],
            [
                'name' => 'Jörg Aderhold',
                'password' => Hash::make('joerg@jaderbass.de'),
            ]
        );

        // Rolle "Admin" zuweisen, falls noch nicht vorhanden
        if (! $joergUser->hasRole('Admin')) {
            $joergUser->assignRole($adminRole);
        }
    }
}
