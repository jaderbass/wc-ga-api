<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Petzl\PetzlDescriptionImportService;
use App\Services\Petzl\PetzlWebsiteMedia;
use Illuminate\Console\Command;
use Throwable;

/**
 * Bilder (und Beschreibungen) für Petzl-Produkte von petzl.com holen.
 *
 * php artisan petzl:fetch-media --dry-run            Petzl-Produkte ohne Bilder
 * php artisan petzl:fetch-media --product-id=9100    einzelne Produkte
 * php artisan petzl:fetch-media --page="4001=https://www.petzl.com/de/de/…/HUIT_SPO_….html"
 *                                                    Seite fest vorgeben (geprüft), Galerie-Bilder übernehmen
 *
 * Bilder landen als Bild-Links in product_images (erstes = Titelbild, falls noch
 * keins da ist); vorhandene Bilder bleiben. Beschreibungen über den vorhandenen
 * Petzl-Beschreibungsabruf – manuell gepflegte oder schon geholte bleiben unverändert,
 * und nur wenn die Seite auch Bilder zu unserer Artikelnummer hat.
 */
class FetchPetzlMediaCommand extends Command
{
    protected $signature = 'petzl:fetch-media
                        {--product-id=* : Nur diese Produkte}
                        {--page=* : Produkt-ID=Seiten-URL auf petzl.com (fest vorgegeben)}
                        {--limit= : Höchstens so viele Produkte}
                        {--skip-description : Nur Bilder, keine Beschreibung}
                        {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Bilder und Beschreibungen für Petzl-Produkte von petzl.com holen';

    public function handle(PetzlWebsiteMedia $media, PetzlDescriptionImportService $descriptions): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $petzl = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', ['petzl'])->value('id');

        $pages = [];

        foreach ((array) $this->option('page') as $pair) {
            [$id, $url] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $pages[(int) $id] = trim($url);
        }

        $query = Product::query()->where('manufacturer_id', $petzl)->with('variations:id,product_id,sku');

        if ($pages !== []) {
            $query->whereIn('id', array_keys($pages));
        } elseif ($this->option('product-id') !== []) {
            $query->whereIn('id', (array) $this->option('product-id'));
        } else {
            $query->doesntHave('images');
        }

        if ($this->option('limit') !== null) {
            $query->limit((int) $this->option('limit'));
        }

        $counts = ['Seite gefunden' => 0, 'nicht gefunden' => 0, 'Bilder' => 0, 'Beschreibungen' => 0];

        foreach ($query->get() as $product) {
            $skus = $product->variations->pluck('sku')->prepend($product->sku)->filter()->values()->all();

            if (isset($pages[$product->id])) {
                $page = $media->page($pages[$product->id]);
            } else {
                $page = $skus === [] ? null : $media->findProductPage($skus);
            }

            if ($page === null) {
                $counts['nicht gefunden']++;
                $this->line("  – {$product->id} {$product->product_name}: keine Seite auf petzl.com");

                continue;
            }

            $counts['Seite gefunden']++;
            $urls = isset($pages[$product->id]) ? $media->galleryImageUrls($page['html']) : $media->imageUrls($page['html'], $skus);
            $counts['Bilder'] += count($urls);
            $this->line("  ✓ {$product->id} {$product->product_name}: ".count($urls).' Bild(er) – '.$page['url']);

            if ($dryRun) {
                foreach ($urls as $url) {
                    $this->line('      '.basename($url));
                }

                continue;
            }

            $hasMain = $product->images()->where('is_main', true)->exists();

            foreach ($urls as $index => $url) {
                ProductImage::query()->firstOrCreate(
                    ['product_id' => $product->id, 'url' => $url],
                    ['is_main' => ! $hasMain && $index === 0],
                );
            }

            // Ohne passendes Bild ist nicht sicher, dass es die richtige Seite ist
            // (unsere Nummer kann auch nur als Zubehör-Empfehlung dort stehen).
            if ($urls === []) {
                continue;
            }

            if (! $this->option('skip-description') && $descriptions->shouldImport($product)) {
                try {
                    $descriptions->importFromUrl($product, $page['url']);
                    $counts['Beschreibungen']++;
                } catch (Throwable $e) {
                    $this->warn("    Beschreibung nicht übernommen: {$e->getMessage()}");
                }
            }

            usleep(300_000);
        }

        $this->newLine();

        foreach ($counts as $label => $count) {
            $this->line(str_pad($label.':', 18).$count);
        }

        return self::SUCCESS;
    }
}
