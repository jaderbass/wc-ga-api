<?php

namespace App\Console\Commands;

use App\Models\ManufacturerDiscountChange;
use App\Services\Pricing\PurchasePriceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fällige Rabattwechsel übernehmen (Stichtag erreicht) und EK neu berechnen.
 * Läuft täglich über den Scheduler.
 */
class ApplyDiscountChangesCommand extends Command
{
    protected $signature = 'prices:apply-discount-changes';

    protected $description = 'Fällige Rabattwechsel der Hersteller übernehmen und EK neu berechnen';

    public function handle(PurchasePriceCalculator $calculator): int
    {
        $due = ManufacturerDiscountChange::query()->due()->with('manufacturer')->orderBy('valid_from')->get();

        if ($due->isEmpty()) {
            $this->line('Keine fälligen Rabattwechsel.');

            return self::SUCCESS;
        }

        foreach ($due as $change) {
            $manufacturer = $change->manufacturer;

            $changed = DB::transaction(function () use ($change, $manufacturer, $calculator): int {
                $manufacturer->forceFill([
                    'purchase_discount_1' => $change->purchase_discount_1,
                    'purchase_discount_2' => $change->purchase_discount_2,
                ])->saveQuietly();

                $change->forceFill(['applied_at' => now()])->save();

                return $calculator->recalculateForManufacturer($manufacturer->refresh());
            });

            $this->line(sprintf(
                '%s: ab %s %s übernommen, %d EK neu berechnet',
                $manufacturer->manufacturer,
                $change->valid_from->format('d.m.Y'),
                PurchasePriceCalculator::describeDiscounts($manufacturer) ?? 'keine Rabatte',
                $changed,
            ));
        }

        return self::SUCCESS;
    }
}
