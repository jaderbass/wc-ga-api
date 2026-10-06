<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use App\Services\ShopComparison\WooSnapshotMatcher;
use App\Services\ShopComparison\WooSnapshotService;
use App\Services\ShopComparison\WooSnapshotStats;
use Illuminate\Console\Command;
use Throwable;

class WooSnapshotCommand extends Command
{
    protected $signature = 'woo:snapshot
        {--shop= : ID des Shops (Standard: der als Standard markierte Shop)}
        {--pause=150 : Pause zwischen API-Seiten in Millisekunden}
        {--keep=5 : Anzahl Momentaufnahmen, die aufbewahrt werden}';

    protected $description = 'Liest alle Produkte und Varianten aus dem WooCommerce-Shop (nur lesend, nichts wird in den Shop geschrieben) und gleicht sie per SKU/EAN mit der Datenbank ab.';

    public function handle(WooSnapshotMatcher $matcher): int
    {
        $shop = $this->option('shop')
            ? Shop::find((int) $this->option('shop'))
            : Shop::where('is_default', true)->first();

        if (! $shop) {
            $this->error('Kein Shop gefunden. Bitte unter "Woo Sync → Shops" einen Standard-Shop festlegen oder --shop=ID angeben.');

            return self::FAILURE;
        }

        $this->info("Lese Shop \"{$shop->name}\" ({$shop->base_url}) – nur lesend, es wird nichts in den Shop geschrieben.");

        $service = new WooSnapshotService(pauseMs: max(0, (int) $this->option('pause')));

        try {
            $snapshot = $service->run($shop, function (int $products, int $variations) {
                if ($products % 50 === 0) {
                    $this->line("  … {$products} Produkte, {$variations} Varianten gelesen");
                }
            });
        } catch (Throwable $e) {
            $this->error('Abbruch beim Lesen: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Gelesen: {$snapshot->products_count} Produkte, {$snapshot->variations_count} Varianten (Momentaufnahme #{$snapshot->id}).");

        $matcher->match($snapshot);
        self::printStats($this, $snapshot);
        $this->prune(max(1, (int) $this->option('keep')));

        return self::SUCCESS;
    }

    public static function printStats(Command $command, WooSnapshot $snapshot): void
    {
        $stats = WooSnapshotStats::for($snapshot);

        $command->newLine();
        $command->info('Abgleich (einfache Produkte und Varianten):');
        $command->table(
            ['Status', 'Anzahl'],
            collect(WooSnapshotItem::MATCH_LABELS)
                ->map(fn ($label, $key) => [$label, $stats['units'][$key] ?? 0])
                ->filter(fn ($row) => $row[1] > 0)
                ->values()
                ->all()
        );

        $command->line('Variable Elternprodukte: '.array_sum($stats['parents']));
        $command->line("Doppelte SKUs im Shop: {$stats['duplicate_skus']}");
        $command->line("Datenbank: {$stats['db_products']} Produkte, davon im Shop gefunden: {$stats['db_products_matched']}, nur in der Datenbank: {$stats['db_products_only']}");
        $command->line('Details im Admin unter "Shop-Abgleich".');
    }

    private function prune(int $keep): void
    {
        $keepIds = WooSnapshot::query()->latest('id')->limit($keep)->pluck('id');

        WooSnapshot::query()->whereNotIn('id', $keepIds)->get()->each->delete();
    }
}
