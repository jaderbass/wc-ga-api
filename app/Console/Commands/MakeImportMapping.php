<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeImportMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:import-mapping {name : Herstellername oder Mapping-Slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erzeugt eine Import-Mapping-Datei unter config/import_mappings mit DocBlock-Vorlage.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        // "Aliens", "aliens", "Aliens Gear" → "aliens_gear"
        $slug = Str::of($name)
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();

        $prettyName = Str::of($name)->trim()->replace('_', ' ')->replace('-', ' ')->title();
        $importerClass = Str::studly($slug) . 'Importer';

        $directory = config_path('import_mappings');
        $filePath = $directory . DIRECTORY_SEPARATOR . $slug . '.php';

        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->error('Konnte das Verzeichnis config/import_mappings nicht erstellen.');
                return self::FAILURE;
            }
        }

        if (file_exists($filePath)) {
            $this->error('Die Datei existiert bereits: ' . $filePath);
            return self::FAILURE;
        }

        $content = $this->buildTemplate($slug, $prettyName, $importerClass);

        if (file_put_contents($filePath, $content) === false) {
            $this->error('Konnte die Datei nicht schreiben: ' . $filePath);
            return self::FAILURE;
        }

        $this->info('Import-Mapping angelegt: ' . $filePath);

        return self::SUCCESS;
    }

    /**
 * Erzeugt den Inhalt der Mapping-Datei inkl. DocBlock.
 */
protected function buildTemplate(string $slug, string $prettyName, string $importerClassBase): string
{
    $path = "config/import_mappings/{$slug}.php";

    // Importer-Klasse nach realer Struktur:
    // App\Imports\ImporterForAliens, ImporterForEdelrid, etc.
    $importerClass = 'App\\Imports\\ImporterFor' . $importerClassBase;

    return <<<PHP
<?php

/**
 * Hersteller-Mapping: {$prettyName}
 *
 * Dieses Mapping sorgt dafür, dass die Rohdaten von {$prettyName}
 * nicht länger wie ein wilder Hersteller-Feed aussehen, sondern brav
 * in unser internes Produktdaten-Schema passen.
 *
 * Pfad: {$path}
 *
 * Verantwortlichkeiten:
 * - Feld-Mapping vom Hersteller-Feed auf interne Attribute
 * - Normalisierung von Farben, Größen, Seriennamen usw.
 * - Vorbereitung zur Bildung von Varianten (z. B. Größe, Farbe, Länge)
 *
 * Hinweis:
 * Wenn {$prettyName} seine Feed-Struktur ändert, wirst du es vermutlich
 * zuerst hier merken – in Form von Fehlern im Import-Log.
 *
 * @mapping-source   {$prettyName} Feed (CSV/XML/API)
 * @mapping-target   InternalProductDTO
 * @see {$importerClass}
 */

return [
    // TODO: Mapping-Konfiguration für {$prettyName} ergänzen.
];

PHP;
    }
}
