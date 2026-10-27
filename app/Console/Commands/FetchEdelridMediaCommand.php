<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductMeta;
use App\Services\Edelrid\EdelridWebsite;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bilder, Beschreibungen, EAN, Gewicht und PDFs für Edelrid-Produkte von edelrid.com holen.
 *
 * php artisan edelrid:fetch-media --dry-run      Bericht, nichts speichern
 * php artisan edelrid:fetch-media                alle Edelrid-Produkte, nur leere Felder füllen
 *
 * 1. Index: alle Produktseiten aus der Sitemap lesen (Artikelnummer → Seite), zwischengespeichert
 *    in storage/app/edelrid/website.json (neu lesen mit --refresh)
 * 2. Je Produkt (über die Artikelnummern seiner Varianten):
 *    - Bilder (erstes = Titelbild), nur wenn das Produkt noch keine Bilder hat
 *    - Beschreibung (Beschreibung + Features + Technische Informationen) und
 *      Kurzbeschreibung (Kurztext von edelrid.com, sonst erster Satz) – nur wenn leer
 *    - EAN und Gewicht je Variante, Gebrauchsanleitung und Konformitätserklärung – nur wenn leer
 */
class FetchEdelridMediaCommand extends Command
{
    protected $signature = 'edelrid:fetch-media
                        {--product-id=* : Nur diese Produkte}
                        {--page=* : Produkt-ID=Seiten-URL auf edelrid.com (geprüft, fest vorgegeben)}
                        {--refresh : Produktseiten neu einlesen (sonst Zwischenspeicher)}
                        {--include-sport : Auch Seiten aus dem Sport-Bereich einlesen}
                        {--dry-run : Nur berichten, nichts speichern}';

    protected $description = 'Edelrid: Bilder, Beschreibungen, EAN/Gewicht und PDFs von edelrid.com übernehmen (nur leere Felder)';

    public const CACHE = 'edelrid/website.json';

    public const MAX_IMAGES = 15;

    /** @var array<string, int> */
    protected array $counts = [];

    public function handle(EdelridWebsite $website): int
    {
        $pages = $this->index($website);
        $bySku = [];

        foreach ($pages as $index => $page) {
            foreach ($page['variants'] as $variant) {
                $bySku[$variant['sku']] ??= $index;
            }
        }

        $this->line(count($pages).' Produktseiten, '.count($bySku).' Artikelnummern auf edelrid.com');

        $manufacturerId = Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', ['edelrid'])->value('id');
        $dryRun = (bool) $this->option('dry-run');
        $this->counts = array_fill_keys(['Produkte', 'Seite gefunden', 'keine Seite (aktiv)', 'keine Seite (ausgelaufen)', 'Bilder', 'Beschreibung', 'Kurzbeschreibung', 'EAN', 'Gewicht', 'Gebrauchsanleitung', 'Konformitätserklärung'], 0);

        $query = Product::query()->where('manufacturer_id', $manufacturerId)->with('variations');

        $fixed = [];

        foreach ((array) $this->option('page') as $pair) {
            [$id, $url] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $fixed[(int) $id] = trim($url);
        }

        if ($fixed !== []) {
            $query->whereIn('id', array_keys($fixed));
        } elseif ($this->option('product-id') !== []) {
            $query->whereIn('id', (array) $this->option('product-id'));
        }

        foreach ($query->get() as $product) {
            $this->counts['Produkte']++;
            $skus = $product->variations->map(fn ($v) => preg_replace('/\D+/', '', (string) $v->sku))
                ->push(preg_replace('/\D+/', '', (string) $product->sku))
                ->filter()->unique()->values();

            if (isset($fixed[$product->id])) {
                $page = str_starts_with($fixed[$product->id], 'https://edelrid.com/') ? $website->page($fixed[$product->id]) : null;

                if ($page === null) {
                    $this->counts['keine Seite (aktiv)']++;

                    continue;
                }

                $this->counts['Seite gefunden']++;
                $this->apply($product, $page, $dryRun);

                continue;
            }

            $index = $skus->map(fn ($sku) => $bySku[$sku] ?? null)->filter(fn ($i) => $i !== null)->first();

            if ($index === null) {
                $this->counts[$product->online_sellable === false ? 'keine Seite (ausgelaufen)' : 'keine Seite (aktiv)']++;

                continue;
            }

            $this->counts['Seite gefunden']++;
            $this->apply($product, $pages[$index], $dryRun);
        }

        $this->report();

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    protected function apply(Product $product, array $page, bool $dryRun): void
    {
        $changes = [];

        // Bilder
        if ($page['images'] !== [] && ! $product->images()->exists()) {
            $this->counts['Bilder']++;

            if (! $dryRun) {
                foreach (array_slice($page['images'], 0, self::MAX_IMAGES) as $i => $url) {
                    ProductImage::query()->firstOrCreate(['product_id' => $product->id, 'url' => $url], ['is_main' => $i === 0]);
                }
            }
        }

        // Beschreibung
        if (blank(strip_tags((string) $product->description)) && filled($page['description'])) {
            $changes['description'] = trim($page['description']
                .($page['features'] ? "\n<h4>Features</h4>\n".$page['features'] : '')
                .($page['technical'] ? "\n<h4>Technische Informationen</h4>\n".$page['technical'] : ''));
            $this->counts['Beschreibung']++;
        }

        if (blank(strip_tags((string) $product->short_description))) {
            $short = $page['teaser'] ?: ($page['description'] ? EdelridWebsite::firstSentence($page['description']) : null);

            if ($short) {
                $changes['short_description'] = $short;
                $this->counts['Kurzbeschreibung']++;
            }
        }

        // PDFs
        foreach (['manual_url' => ['Gebrauchsanleitung', 'Gebrauchsanleitung'], 'declaration_of_compliance' => ['Konformitätserklärung', 'Konformitätserklärung']] as $field => [$needle, $label]) {
            $pdfs = collect($page['pdfs'])->filter(fn ($url, $title) => str_contains(mb_strtolower($title), mb_strtolower($needle)));
            // EU-Konformitätserklärung vor anderen (z. B. ANSI)
            $link = $pdfs->first(fn ($url, $title) => str_contains(mb_strtolower($title), 'eu-')) ?? $pdfs->first();

            if ($link && blank($product->{$field})) {
                $changes[$field] = $link;
                $this->counts[$label]++;
            }
        }

        if (! $dryRun && $changes !== []) {
            $product->forceFill($changes)->save();
        }

        // EAN / Gewicht je Variante
        $variants = collect($page['variants'])->keyBy('sku');

        foreach ($product->variations as $variation) {
            $data = $variants->get(preg_replace('/\D+/', '', (string) $variation->sku));

            if ($data === null) {
                continue;
            }

            $fill = [];

            if (blank($variation->ean) && $data['gtin']) {
                $fill['ean'] = $data['gtin'];
                $this->counts['EAN']++;
            }

            if (blank($variation->weight) && $data['weight']) {
                $fill['weight'] = $data['weight'];
                $this->counts['Gewicht']++;
            }

            if (! $dryRun && $fill !== []) {
                $variation->forceFill($fill)->saveQuietly();
            }
        }

        if (! $dryRun) {
            ProductMeta::query()->updateOrCreate(
                ['product_id' => $product->id, 'variation_id' => null, 'scope' => 'product', 'key' => 'edelrid_url'],
                ['value' => $page['url']],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function index(EdelridWebsite $website): array
    {
        $disk = Storage::disk('local');

        if (! $this->option('refresh') && $disk->exists(self::CACHE)) {
            return json_decode((string) $disk->get(self::CACHE), true) ?: [];
        }

        $urls = $website->productUrls((bool) $this->option('include-sport'));
        $this->line('Lese '.count($urls).' Produktseiten von edelrid.com …');

        $pages = [];
        $bar = $this->output->createProgressBar(count($urls));

        foreach ($urls as $url) {
            $page = $website->page($url);

            if ($page !== null && $page['variants'] !== []) {
                $pages[] = $page;
            }

            $bar->advance();
            usleep(250_000);
        }

        $bar->finish();
        $this->newLine();
        $disk->put(self::CACHE, json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $pages;
    }

    protected function report(): void
    {
        $this->newLine();
        $this->line(($this->option('dry-run') ? '[Probelauf – nichts gespeichert] ' : '').'Ergebnis:');

        foreach ($this->counts as $label => $count) {
            $color = match (true) {
                $label === 'keine Seite (aktiv)' => $count > 0 ? 'red' : 'green',
                in_array($label, ['Produkte', 'keine Seite (ausgelaufen)'], true) => 'default',
                default => $count > 0 ? 'green' : 'default',
            };

            $this->line(str_pad($label.':', 24).($color === 'default' ? $count : "<fg={$color}>{$count}</>"));
        }
    }
}
