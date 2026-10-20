<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Produktdaten und Lagerbestand aus dem Aliens-B2B-Bereich herunterladen.
 *
 * php artisan aliens:b2b-fetch                 beide Dateien
 * php artisan aliens:b2b-fetch lagerbestand    nur eine
 *
 * Ablage: storage/app/imports/aliens-b2b/JJJJ-MM-TT-<Datei> und <name>-aktuell.xlsx.
 * Verarbeitet wird hier nichts. Der Schlüssel kommt aus der .env (ALIENS_B2B_KEY)
 * und erscheint nie in Ausgabe oder Log.
 */
class FetchAliensB2bCommand extends Command
{
    protected $signature = 'aliens:b2b-fetch
                        {files?* : produkte und/oder lagerbestand (Standard: beide)}';

    protected $description = 'Produktdaten und Lagerbestand aus dem Aliens-B2B-Bereich herunterladen';

    public function handle(): int
    {
        $key = (string) config('aliens_b2b.key');

        if ($key === '') {
            $this->error('ALIENS_B2B_KEY fehlt in der .env.');

            return self::FAILURE;
        }

        $files = config('aliens_b2b.files', []);
        $wanted = $this->argument('files') ?: array_keys($files);
        $directory = trim((string) config('aliens_b2b.directory'), '/');
        $disk = Storage::disk('local');
        $failed = false;

        foreach ($wanted as $name) {
            $file = $files[$name] ?? null;

            if ($file === null) {
                $this->error("Unbekannt: {$name} (erlaubt: ".implode(', ', array_keys($files)).')');
                $failed = true;

                continue;
            }

            try {
                $response = Http::timeout(300)->get(rtrim((string) config('aliens_b2b.base_url'), '/').'/'.$file, ['key' => $key]);
            } catch (Throwable) {
                // Meldung enthält die URL samt Schlüssel – deshalb nicht ausgeben
                $this->error("{$file}: keine Verbindung.");
                $failed = true;

                continue;
            }

            $body = $response->body();

            // Excel-Dateien sind ZIP-Archive ("PK"); eine HTML-Seite heißt: Schlüssel/Zugang abgelehnt
            if (! $response->successful() || ! str_starts_with($body, 'PK')) {
                $this->error("{$file}: Abruf fehlgeschlagen (Status {$response->status()}).");
                $failed = true;

                continue;
            }

            $dated = $directory.'/'.now()->format('Y-m-d').'-'.$file;
            $latest = $directory.'/'.$name.'-aktuell.'.pathinfo($file, PATHINFO_EXTENSION);

            $disk->put($dated, $body);
            $disk->put($latest, $body);

            $this->info("{$file}: ".round(strlen($body) / 1024).' KB → storage/app/'.$latest);
        }

        $this->cleanUp($directory);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Datierte Downloads, die älter als keep_days sind, entfernen ("-aktuell" bleibt).
     */
    protected function cleanUp(string $directory): void
    {
        $limit = now()->subDays((int) config('aliens_b2b.keep_days', 14))->format('Y-m-d');
        $disk = Storage::disk('local');

        foreach ($disk->files($directory) as $path) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})-/', basename($path), $match) === 1 && $match[1] < $limit) {
                $disk->delete($path);
            }
        }
    }
}
