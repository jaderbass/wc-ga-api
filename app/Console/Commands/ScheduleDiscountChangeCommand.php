<?php

namespace App\Console\Commands;

use App\Models\Manufacturer;
use App\Models\ManufacturerDiscountChange;
use App\Services\Pricing\PurchasePriceCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Rabattwechsel zu einem Stichtag planen.
 *
 * php artisan prices:schedule-discount Petzl 35 5 --from=2027-01-01 --note="Kondition 2027"
 *
 * Am Stichtag übernimmt prices:apply-discount-changes (täglich) die Rabatte und rechnet die EK neu.
 */
class ScheduleDiscountChangeCommand extends Command
{
    protected $signature = 'prices:schedule-discount
                        {manufacturer : Hersteller (ID oder Name)}
                        {discount1 : Rabatt 1 in %}
                        {discount2=0 : Rabatt 2 in % (vom bereits rabattierten Preis)}
                        {--from= : Gültig ab (JJJJ-MM-TT)}
                        {--note= : Notiz, z. B. Quelle der Kondition}';

    protected $description = 'Rabattwechsel eines Herstellers zu einem Stichtag planen';

    public function handle(): int
    {
        $name = (string) $this->argument('manufacturer');
        $manufacturer = is_numeric($name)
            ? Manufacturer::find((int) $name)
            : Manufacturer::query()->whereRaw('LOWER(manufacturer) = ?', [mb_strtolower($name)])->first();

        if (! $manufacturer) {
            $this->error("Hersteller nicht gefunden: {$name}");

            return self::FAILURE;
        }

        $from = $this->option('from');

        if (! is_string($from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $this->error('Bitte --from=JJJJ-MM-TT angeben.');

            return self::FAILURE;
        }

        $change = ManufacturerDiscountChange::query()->updateOrCreate(
            ['manufacturer_id' => $manufacturer->id, 'valid_from' => Carbon::parse($from)->toDateString(), 'applied_at' => null],
            [
                'purchase_discount_1' => (float) $this->argument('discount1'),
                'purchase_discount_2' => (float) $this->argument('discount2'),
                'note' => $this->option('note'),
            ],
        );

        $this->line(sprintf(
            '%s: ab %s → %s + %s (aktuell: %s)',
            $manufacturer->manufacturer,
            $change->valid_from->format('d.m.Y'),
            PurchasePriceCalculator::formatPercent($change->purchase_discount_1),
            PurchasePriceCalculator::formatPercent($change->purchase_discount_2),
            PurchasePriceCalculator::describeDiscounts($manufacturer) ?? 'keine Rabatte',
        ));

        return self::SUCCESS;
    }
}
