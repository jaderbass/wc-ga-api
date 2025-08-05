<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Seeder für Standardrollen.
 *
 * Legt die grundlegenden Rollen (Admin, Editor, User) für den Guard "web" an.
 * Dieser Seeder sollte nach jeder Migration ausgeführt werden,
 * damit die Rollen für Benutzerzuweisungen verfügbar sind.
 */
class RoleSeeder extends Seeder
{
    /**
     * Führt den Seeder aus.
     *
     * Erstellt, falls nicht vorhanden, die Standardrollen:
     * - Admin
     * - Editor
     * - User
     *
     * @return void
     */
    public function run(): void
    {
        $roles = ['Admin', 'Editor', 'User'];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web']
            );
        }
    }
}
