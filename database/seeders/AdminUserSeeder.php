<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Seeder für einen initialen Administrator.
 *
 * Die Zugangsdaten werden ausschließlich über die Konfiguration
 * bzw. die zugehörigen Umgebungsvariablen bereitgestellt.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Legt den initialen Admin-Benutzer an und weist ihm die Rolle "Admin" zu.
     *
     * Erwartet die Werte ADMIN_NAME, ADMIN_EMAIL und ADMIN_PASSWORD.
     * Existiert bereits ein Benutzer mit der konfigurierten E-Mail-Adresse,
     * wird dieser nicht neu angelegt und sein Passwort nicht überschrieben.
     *
     * @throws RuntimeException Wenn erforderliche Admin-Konfiguration fehlt.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');
        $name = config('admin.name');

        if (! $email || ! $password || ! $name) {
            throw new RuntimeException(
                'ADMIN_NAME, ADMIN_EMAIL und ADMIN_PASSWORD müssen gesetzt sein.'
            );
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        if (! $user->hasRole('Admin')) {
            $user->assignRole($adminRole);
        }
    }
}
