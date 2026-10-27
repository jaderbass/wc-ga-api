<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Produktbilder aus einem Ordner übernehmen und selbst ausliefern.
 *
 * Aufbau: <ordner>/<Produkt-ID>/01.jpg, 02.jpg … (Reihenfolge = Dateiname, erstes = Titelbild)
 * php artisan products:import-local-images storage/app/imports/bilder --dry-run
 *
 * Standard: nur für Produkte ohne Bilder; --append hängt an vorhandene Bilder an.
 */
class ImportLocalProductImagesCommand extends Command
{
    protected $signature = 'products:import-local-images
                        {directory : Ordner mit Unterordnern je Produkt-ID}
                        {--append : Auch an Produkte mit vorhandenen Bildern anhängen}
                        {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Produktbilder aus Ordnern je Produkt-ID übernehmen (Auslieferung über /product-image/…)';

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function handle(): int
    {
        $directory = (string) $this->argument('directory');
        $directory = is_dir($directory) ? $directory : base_path($directory);

        if (! is_dir($directory)) {
            $this->error("Ordner nicht gefunden: {$directory}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = ['Produkte' => 0, 'Bilder' => 0, 'übersprungen (hat Bilder)' => 0, 'Produkt fehlt' => 0];

        foreach (glob(rtrim($directory, '/\\').'/*', GLOB_ONLYDIR) ?: [] as $folder) {
            $id = basename($folder);

            if (! ctype_digit($id)) {
                continue;
            }

            $product = Product::query()->find((int) $id);

            if ($product === null) {
                $counts['Produkt fehlt']++;
                $this->line("  <fg=red>–</> {$id}: Produkt nicht gefunden");

                continue;
            }

            if ($product->images()->exists() && ! $this->option('append')) {
                $counts['übersprungen (hat Bilder)']++;

                continue;
            }

            $files = array_values(array_filter(glob($folder.'/*') ?: [], fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::EXTENSIONS, true)));
            sort($files);

            if ($files === []) {
                continue;
            }

            $counts['Produkte']++;
            $counts['Bilder'] += count($files);
            $this->line("  <fg=green>✓</> {$product->id} {$product->product_name}: ".count($files).' Bild(er)');

            if ($dryRun) {
                continue;
            }

            $hasMain = $product->images()->where('is_main', true)->exists();

            foreach ($files as $i => $file) {
                $name = strtolower((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($file)));
                Storage::disk('public')->put("product-images/{$product->id}/{$name}", (string) file_get_contents($file));

                ProductImage::query()->firstOrCreate(
                    ['product_id' => $product->id, 'url' => route('product-image', ['product' => $product->id, 'file' => $name])],
                    ['is_main' => ! $hasMain && $i === 0],
                );
            }
        }

        $this->newLine();

        foreach ($counts as $label => $count) {
            $color = match (true) {
                $label === 'Produkt fehlt' && $count > 0 => 'red',
                in_array($label, ['Produkte', 'Bilder'], true) && $count > 0 => 'green',
                default => null,
            };

            $this->line(str_pad($label.':', 28).($color ? "<fg={$color}>{$count}</>" : $count));
        }

        return self::SUCCESS;
    }
}
