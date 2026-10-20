<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Betriebsschalter der Datenbank.
 *
 * Abgeschaltet: Nur geschützte Konten kommen ins Backend, alle anderen Anfragen
 * (Webhooks, API, sonstige Routen) bekommen 503, Queue-Worker pausieren und
 * Artisan führt nur noch Wartungsbefehle aus.
 *
 * Der Zustand liegt als Datei im storage-Verzeichnis, damit ein Deploy
 * (artisan up) ihn nicht zurücksetzt.
 */
class SystemLock
{
    /** Artisan-Befehle, die auch im abgeschalteten Zustand laufen dürfen. */
    public const ALLOWED_COMMANDS = [
        'system:lock',
        'list',
        'help',
        'about',
        'tinker',
        'down',
        'up',
        'optimize',
        'optimize:clear',
        'config:cache',
        'config:clear',
        'route:cache',
        'route:clear',
        'view:cache',
        'view:clear',
        'event:cache',
        'event:clear',
        'cache:clear',
        'storage:link',
        'package:discover',
        'filament:upgrade',
        'vendor:publish',
        'queue:restart',
        'migrate:status',
    ];

    public static function path(): string
    {
        return config('admin_access.lock_file') ?: storage_path('app/system-lock.json');
    }

    public static function isLocked(): bool
    {
        return File::exists(self::path());
    }

    /**
     * @return array{locked_at?: string, locked_by?: int|null}|null
     */
    public static function info(): ?array
    {
        if (! self::isLocked()) {
            return null;
        }

        return json_decode((string) File::get(self::path()), true) ?: [];
    }

    public static function lock(?User $by = null): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode([
            'locked_at' => now()->toIso8601String(),
            'locked_by' => $by?->getKey(),
        ]));

        Log::warning('Datenbank abgeschaltet', ['user_id' => $by?->getKey()]);
    }

    public static function unlock(?User $by = null): void
    {
        File::delete(self::path());

        Log::warning('Datenbank eingeschaltet', ['user_id' => $by?->getKey()]);
    }

    public static function allowsCommand(?string $command): bool
    {
        return $command === null || in_array($command, self::ALLOWED_COMMANDS, true);
    }
}
