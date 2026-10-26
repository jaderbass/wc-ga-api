<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hersteller-Logos aus einem Ordner übernehmen (für das Dashboard).
 *
 * Dateiname = Herstellername als Slug, z. B. petzl.svg, singing-rock.png, kong.svg.
 * php artisan manufacturers:import-logos storage/app/imports/logos --dry-run
 */
class ImportManufacturerLogosCommand extends Command
{
    protected $signature = 'manufacturers:import-logos
                        {directory : Ordner mit den Logo-Dateien}
                        {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Hersteller-Logos (Dateiname = Herstellername) in die Datenbank übernehmen';

    public const EXTENSIONS = ['svg', 'png', 'webp', 'jpg', 'jpeg'];

    public function handle(): int
    {
        $directory = (string) $this->argument('directory');
        $directory = is_dir($directory) ? $directory : base_path($directory);

        if (! is_dir($directory)) {
            $this->error("Ordner nicht gefunden: {$directory}");

            return self::FAILURE;
        }

        $files = [];

        foreach (glob(rtrim($directory, '/\\').'/*') ?: [] as $path) {
            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                $files[Str::slug(pathinfo($path, PATHINFO_FILENAME))] = $path;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $used = [];

        foreach (Manufacturer::query()->orderBy('manufacturer')->get() as $manufacturer) {
            $slug = Str::slug((string) $manufacturer->manufacturer);
            $path = $files[$slug] ?? null;

            if ($path === null) {
                continue;
            }

            $used[$slug] = true;
            $target = 'manufacturer-logos/'.$slug.'.'.strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $this->line("  ✓ {$manufacturer->manufacturer} ← ".basename($path));

            if (! $dryRun) {
                Storage::disk('public')->put($target, (string) file_get_contents($path));
                $manufacturer->forceFill(['logo_path' => $target])->save();
            }
        }

        foreach (array_diff_key($files, $used) as $path) {
            $this->warn('  – kein Hersteller passt zu '.basename($path));
        }

        if (! $dryRun) {
            DashboardStats::flush();
        }

        $this->newLine();
        $this->line(count($used).' Logo(s) '.($dryRun ? 'würden übernommen' : 'übernommen').'.');

        return self::SUCCESS;
    }
}
