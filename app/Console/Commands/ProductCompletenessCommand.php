<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\Dashboard\DashboardStats;
use Illuminate\Console\Command;

/**
 * Vollständigkeit je Hersteller: aktive Produkte mit Bild, Kurz- und ausführlicher Beschreibung.
 * Lücken rot, vollständig grün.
 *
 * php artisan products:completeness
 * php artisan products:completeness --manufacturer=Petzl --list
 */
class ProductCompletenessCommand extends Command
{
    protected $signature = 'products:completeness
                        {--manufacturer= : Nur dieser Hersteller (Name oder ID)}
                        {--list : Produkte mit Lücken auflisten}
                        {--all : Auch ausgelaufene/offline Produkte zählen}';

    protected $description = 'Bilder und Beschreibungen je Hersteller prüfen (Lücken rot)';

    public function handle(DashboardStats $stats): int
    {
        DashboardStats::flush();
        $images = $stats->imageCounts();
        $filter = $this->option('manufacturer');

        $manufacturers = Manufacturer::query()
            ->has('products')
            ->when(filled($filter), fn ($q) => is_numeric($filter)
                ? $q->whereKey((int) $filter)
                : $q->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower((string) $filter)]))
            ->orderBy('manufacturer')
            ->get();

        $rows = [];
        $gaps = [];

        foreach ($manufacturers as $manufacturer) {
            $products = Product::query()
                ->where('manufacturer_id', $manufacturer->id)
                ->when(! $this->option('all'), fn ($q) => $q->where(fn ($w) => $w->whereNull('online_sellable')->orWhere('online_sellable', true)))
                ->get(['id', 'product_name', 'short_description', 'description']);

            if ($products->isEmpty()) {
                continue;
            }

            $missing = ['Bild' => [], 'Kurz' => [], 'Lang' => []];

            foreach ($products as $product) {
                if (! isset($images[$product->id])) {
                    $missing['Bild'][] = $product;
                }

                if (blank(trim(strip_tags((string) $product->short_description)))) {
                    $missing['Kurz'][] = $product;
                }

                if (blank(trim(strip_tags((string) $product->description)))) {
                    $missing['Lang'][] = $product;
                }
            }

            $cell = fn (array $list) => $list === [] ? '<fg=green>✓</>' : '<fg=red>-'.count($list).'</>';
            $rows[] = [$manufacturer->manufacturer, $products->count(), $cell($missing['Bild']), $cell($missing['Kurz']), $cell($missing['Lang'])];

            foreach ($missing as $type => $list) {
                foreach ($list as $product) {
                    $gaps[$product->id] ??= ['id' => $product->id, 'name' => $product->product_name, 'missing' => []];
                    $gaps[$product->id]['missing'][] = $type;
                }
            }
        }

        $this->table(['Hersteller', $this->option('all') ? 'Produkte' : 'aktiv', 'ohne Bild', 'ohne Kurz', 'ohne Lang'], $rows);

        if ($this->option('list') && $gaps !== []) {
            $this->newLine();

            foreach ($gaps as $gap) {
                $this->line("  <fg=red>{$gap['id']}</> {$gap['name']} – fehlt: ".implode(', ', $gap['missing']));
            }
        }

        return self::SUCCESS;
    }
}
