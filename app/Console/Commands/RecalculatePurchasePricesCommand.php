<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Services\Pricing\PurchasePriceCalculator;
use Illuminate\Console\Command;

/**
 * Berechnet die EK (Listenpreis abzüglich Rabattstufen) neu – für alle
 * Hersteller oder einen. EK, die direkt aus einer Preisliste stammen
 * (z. B. Aliens HEK), bleiben unverändert.
 */
class RecalculatePurchasePricesCommand extends Command
{
    protected $signature = 'prices:recalculate-purchase {--manufacturer= : Nur diesen Hersteller (ID oder Name)}';

    protected $description = 'EK aus Listenpreis und Rabattstufen der Hersteller neu berechnen';

    public function handle(PurchasePriceCalculator $calculator): int
    {
        $filter = $this->option('manufacturer');

        $manufacturers = Manufacturer::query()
            ->when(filled($filter), fn ($query) => is_numeric($filter)
                ? $query->whereKey((int) $filter)
                : $query->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower((string) $filter)]))
            ->orderBy('manufacturer')
            ->get();

        if ($manufacturers->isEmpty()) {
            $this->warn('Kein passender Hersteller gefunden.');

            return self::FAILURE;
        }

        foreach ($manufacturers as $manufacturer) {
            $changed = $calculator->recalculateForManufacturer($manufacturer);
            $discounts = PurchasePriceCalculator::describeDiscounts($manufacturer) ?? 'keine Rabatte';

            if ($changed > 0 || filled($filter)) {
                $this->line("{$manufacturer->manufacturer} ({$discounts}): {$changed} EK neu berechnet");
            }
        }

        return self::SUCCESS;
    }
}
