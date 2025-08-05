<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Erstellt oder aktualisiert einen Benutzer und gibt ihm Admin-Rechte.
 *
 * Nutzung:
 * php artisan make:admin {email} {--name=} {--password=}
 */
class MakeAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin {email : E-Mail-Adresse des Benutzers} 
                                         {--name= : Name des Benutzers (nur für neue Benutzer)} 
                                         {--password= : Passwort (nur für neue Benutzer)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erstellt oder aktualisiert einen Benutzer und weist ihm Admin-Rechte zu.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? 'Administrator';
        $password = $this->option('password') ?? 'password123';

        $role = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $user->assignRole($role);

        $this->info("Benutzer {$user->email} ist jetzt Admin.");

        return Command::SUCCESS;
    }
}
