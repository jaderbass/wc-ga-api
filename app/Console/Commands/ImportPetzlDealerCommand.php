<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Petzl\PetzlDealerImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artikel aus dem Petzl-Händlerportal abgleichen (Daten als JSON, siehe PetzlDealerImport).
 *
 * php artisan petzl:dealer-import storage/app/imports/2026-10-26-Petzl-Dealer-Beleuchtung.json --dry-run
 */
class ImportPetzlDealerCommand extends Command
{
    protected $signature = 'petzl:dealer-import
                        {file : JSON aus dem Petzl-Händlerportal}
                        {--dry-run : Nur berichten, nichts anlegen}
                        {--author= : E-Mail des Autors neuer Produkte (Standard: erster Admin)}';

    protected $description = 'Fehlende Petzl-Artikel aus dem Händlerportal anlegen, Lager/Status als Meta speichern';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);
        $dryRun = (bool) $this->option('dry-run');
        $json = (string) file_get_contents($path);
        $readAt = (string) (json_decode($json, true)['read_at'] ?? '');

        $email = $this->option('author');
        $authorId = is_string($email) && $email !== ''
            ? User::query()->where('email', $email)->value('id')
            : User::role('Admin')->orderBy('id')->value('id');

        $import = new PetzlDealerImport;
        $rows = PetzlDealerImport::rows($json);

        $this->line(($dryRun ? '[Probelauf – es wird nichts gespeichert] ' : '').count($rows).' Zeilen aus '.basename($path));

        DB::transaction(fn () => $import->run($rows, $dryRun, $authorId, $readAt));

        $this->newLine();

        foreach ($import->counts as $label => $count) {
            $this->line(str_pad($label.':', 24).$count);
        }

        $this->newLine();

        foreach ($import->created as $line) {
            $this->line('  '.$line);
        }

        if (! $dryRun && $import->counts['neues Produkt'] + $import->counts['neue Variante'] > 0) {
            $this->newLine();
            $this->line('Danach: php artisan products:rebuild-names');
        }

        return self::SUCCESS;
    }
}
