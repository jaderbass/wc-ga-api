<?php

namespace App\Console\Commands;

use App\Support\SystemLock;
use Illuminate\Console\Command;

/**
 * Notzugang zum Betriebsschalter über den Server, z. B. wenn das Backend
 * nicht erreichbar ist.
 *
 * php artisan system:lock          Status anzeigen
 * php artisan system:lock off      Datenbank abschalten
 * php artisan system:lock on       Datenbank einschalten
 */
class SystemLockCommand extends Command
{
    protected $signature = 'system:lock {state? : on oder off}';

    protected $description = 'Datenbank an- oder abschalten (Betriebsschalter)';

    public function handle(): int
    {
        $state = $this->argument('state');

        if ($state === 'off') {
            SystemLock::lock();
        } elseif ($state === 'on') {
            SystemLock::unlock();
        } elseif ($state !== null) {
            $this->error('Erlaubt: on, off oder ohne Angabe für den Status.');

            return self::INVALID;
        }

        $info = SystemLock::info();

        $this->line($info === null
            ? 'Datenbank ist eingeschaltet.'
            : 'Datenbank ist abgeschaltet (seit '.($info['locked_at'] ?? '?').').');

        return self::SUCCESS;
    }
}
