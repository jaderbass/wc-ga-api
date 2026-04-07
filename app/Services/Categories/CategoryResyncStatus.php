<?php

namespace App\Services\Categories;

use App\Models\CategoryResyncRun;

class CategoryResyncStatus
{
    /**
     * Liefert den neuesten Resync-Lauf.
     */
    public static function latest(): ?CategoryResyncRun
    {
        return CategoryResyncRun::query()
            ->latest('id')
            ->first();
    }

    /**
     * Prüft, ob aktuell ein Resync läuft.
     */
    public static function isRunning(): bool
    {
        return static::latest()?->status === 'running';
    }

    /**
     * Prüft, ob ein Resync eingeplant ist.
     */
    public static function isQueued(): bool
    {
        return static::latest()?->status === 'queued';
    }

    /**
     * Liefert den Fortschritt in Prozent (0–100).
     */
    public static function progress(): ?int
    {
        $run = static::latest();

        if (! $run || $run->total === 0) {
            return null;
        }

        return (int) round(($run->processed / $run->total) * 100);
    }

    /**
     * Liefert einen kurzen, UI-tauglichen Status-Text.
     */
    public static function text(): ?string
    {
        $run = static::latest();

        if (! $run) {
            return null;
        }

        return match ($run->status) {
            'queued' => 'Neuzuordnung eingeplant',
            'running' => static::runningText($run),
            // 'finished' => 'Neuzuordnung abgeschlossen',
            // 'failed' => 'Neuzuordnung fehlgeschlagen',
            default => null,
        };
    }

    /**
     * Liefert Detailtext für laufenden Resync.
     */
    protected static function runningText(CategoryResyncRun $run): string
    {
        if ($run->total > 0) {
            return sprintf(
                'Neuzuordnung läuft (%d / %d)',
                $run->processed,
                $run->total
            );
        }

        return 'Neuzuordnung läuft';
    }

    /**
     * Liefert den Status als einfachen Key (für UI/Badges).
     */
    public static function status(): ?string
    {
        return static::latest()?->status;
    }

    /**
     * Prüft, ob überhaupt ein Resync vorhanden ist.
     */
    public static function exists(): bool
    {
        return static::latest() !== null;
    }
}
