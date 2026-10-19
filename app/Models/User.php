<?php

namespace App\Models;

use App\Support\SystemLock;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Guard-Name für Spatie Permission
     * Muss zum verwendeten Auth-Guard passen (z.B. 'web' oder 'filament').
     */
    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Geschützte Konten: Löschen und Ändern nur durch das Konto selbst
     * (siehe config/admin_access.php). Greift auch bei der Massenlöschung, die
     * keine Einzelprüfung über die Policy macht.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): bool {
            return ! $user->isProtectedAccount() || $user->isCurrentUser();
        });

        static::updating(function (User $user): bool {
            // Ohne Anmeldung (Login-Vorgang, Konsole) bleibt Ändern möglich,
            // damit sich der Zugang notfalls auf dem Server zurücksetzen lässt.
            return ! $user->isProtectedAccount() || ! auth()->check() || $user->isCurrentUser();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isProtectedAccount()) {
            return true;
        }

        // Datenbank abgeschaltet: nur noch geschützte Konten
        return ! SystemLock::isLocked()
            && $this->hasAnyRole(config('admin_access.panel_roles', []));
    }

    /**
     * Geschütztes Konto? Verglichen wird der SHA-256 der gespeicherten E-Mail
     * (die Adresse selbst steht nicht im Code). Die gespeicherte statt der
     * neuen E-Mail, damit ein Umbenennen den Schutz nicht aufhebt.
     */
    public function isProtectedAccount(): bool
    {
        $email = mb_strtolower(trim((string) ($this->getOriginal('email') ?? $this->email)));

        if ($email === '') {
            return false;
        }

        $hashes = array_map('strtolower', config('admin_access.protected_email_hashes', []));

        return in_array(hash('sha256', $email), $hashes, true);
    }

    public function isCurrentUser(): bool
    {
        return $this->exists && auth()->id() === $this->getKey();
    }
}
