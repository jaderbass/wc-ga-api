<?php

namespace App\Services\Pricing;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductVariation;

/**
 * Einkaufspreis (EK) aus Listenpreis und zwei Rabattstufen, nacheinander:
 *
 *   EK = Listenpreis × (1 − Rabatt 1) × (1 − Rabatt 2)
 *
 * Petzl: 73,50 € × 0,65 × 0,95 = 45,39 € (nicht 40 % auf einmal).
 * Gerundet wird erst am Ende auf ganze Cent.
 *
 * Rabatte werden geerbt: Variante → Produkt → Hersteller. Ein Wert am
 * Produkt bzw. an der Variante ist eine Sonderkondition.
 *
 * Herkunft des EK (purchase_price_source):
 * - calculated: Listenpreis − Rabatte, wird bei Änderungen neu berechnet
 * - pricelist:  stand so in der Preisliste (z. B. Aliens HEK)
 * - manual:     von Hand eingetragen, wird nie automatisch überschrieben
 */
class PurchasePriceCalculator
{
    public const SOURCE_CALCULATED = 'calculated';

    public const SOURCE_PRICELIST = 'pricelist';

    public const SOURCE_MANUAL = 'manual';

    public static function calculate(?int $listPriceCents, mixed $discount1, mixed $discount2): ?int
    {
        if ($listPriceCents === null || $listPriceCents <= 0) {
            return null;
        }

        // exakt in Ganzzahlen rechnen (Rabatte in Hundertstel-Prozent), kaufmännisch runden
        $keep1 = 10000 - (int) round(self::percent($discount1) * 100);
        $keep2 = 10000 - (int) round(self::percent($discount2) * 100);

        return intdiv($listPriceCents * $keep1 * $keep2 + 50_000_000, 100_000_000);
    }

    /**
     * Wirksame Rabatte eines Produkts bzw. einer Variante (geerbt).
     *
     * @return array{0: ?float, 1: ?float, 2: array{0: string, 1: string}} [Rabatt 1, Rabatt 2, [Herkunft 1, Herkunft 2]]
     */
    public static function effectiveDiscounts(Product|ProductVariation $record): array
    {
        $chain = [];

        if ($record instanceof ProductVariation) {
            $chain['Variante'] = $record;
            $chain['Produkt'] = $record->product;
        } else {
            $chain['Produkt'] = $record;
        }

        $manufacturer = ($chain['Produkt'] ?? null)?->manufacturer;
        $chain['Hersteller'] = $manufacturer;

        $result = [null, null, ['', '']];

        foreach (['purchase_discount_1', 'purchase_discount_2'] as $index => $field) {
            foreach ($chain as $label => $level) {
                $value = $level?->{$field};

                if ($value !== null && $value !== '') {
                    $result[$index] = (float) $value;
                    $result[2][$index] = $label;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Beim Speichern eines Produkts bzw. einer Variante: EK und Herkunft festlegen.
     *
     * - EK von außen geändert (Formular, Import): Herkunft bleibt, wenn sie
     *   mitgesetzt wurde; sonst "calculated", falls der EK genau der Berechnung
     *   entspricht, ansonsten "manual" (Sonderpreis)
     * - Listenpreis oder Rabatt geändert und EK wird berechnet: neu berechnen
     */
    public static function applyOnSaving(Product|ProductVariation $record): void
    {
        // geerbte Rabatte immer vom aktuellen Stand lesen (Produkt kann sich geändert haben)
        if ($record instanceof ProductVariation) {
            $record->unsetRelation('product');
        } else {
            $record->unsetRelation('manufacturer');
        }

        [$discount1, $discount2] = self::effectiveDiscounts($record);
        $calculated = self::calculate($record->list_price_cents, $discount1, $discount2);

        if ($record->isDirty('purchase_price_cents')) {
            if (! $record->isDirty('purchase_price_source')) {
                $record->purchase_price_source = $record->purchase_price_cents === null
                    ? null
                    : ($record->purchase_price_cents === $calculated ? self::SOURCE_CALCULATED : self::SOURCE_MANUAL);
            }

            return;
        }

        $inputsChanged = $record->isDirty(['list_price_cents', 'purchase_discount_1', 'purchase_discount_2', 'purchase_price_source']);
        $isCalculated = in_array($record->purchase_price_source, [null, self::SOURCE_CALCULATED], true);

        if ($inputsChanged && $isCalculated && $record->list_price_cents !== null) {
            $record->purchase_price_cents = $calculated;
            $record->purchase_price_source = self::SOURCE_CALCULATED;
        }
    }

    /**
     * EK aller berechneten Produkte und Varianten eines Herstellers neu berechnen
     * (nach Änderung der Hersteller-Rabatte). "manual" und "pricelist" bleiben.
     *
     * @return int Anzahl geänderter Datensätze
     */
    public function recalculateForManufacturer(Manufacturer $manufacturer): int
    {
        $productIds = Product::query()->where('manufacturer_id', $manufacturer->id)->select('id');

        return $this->recalculate(Product::query()->where('manufacturer_id', $manufacturer->id)->with('manufacturer'))
            + $this->recalculate(ProductVariation::query()->whereIn('product_id', $productIds)->with('product.manufacturer'));
    }

    /**
     * EK der berechneten Varianten eines Produkts neu berechnen (nach Änderung der Produkt-Rabatte).
     */
    public function recalculateVariations(Product $product): int
    {
        return $this->recalculate(ProductVariation::query()->where('product_id', $product->id)->with('product.manufacturer'));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product|ProductVariation>  $query
     */
    protected function recalculate($query): int
    {
        $changed = 0;

        $query
            ->whereNotNull('list_price_cents')
            ->where(fn ($q) => $q->whereNull('purchase_price_source')->orWhere('purchase_price_source', self::SOURCE_CALCULATED))
            ->chunkById(500, function ($records) use (&$changed): void {
                foreach ($records as $record) {
                    [$discount1, $discount2] = self::effectiveDiscounts($record);
                    $purchase = self::calculate($record->list_price_cents, $discount1, $discount2);

                    if ($record->purchase_price_cents !== $purchase || $record->purchase_price_source !== self::SOURCE_CALCULATED) {
                        $record->forceFill([
                            'purchase_price_cents' => $purchase,
                            'purchase_price_source' => self::SOURCE_CALCULATED,
                        ])->saveQuietly();
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    /**
     * Für die Anzeige: "35 % + 5 %" bzw. null ohne Rabatt.
     */
    public static function describeDiscounts(?Manufacturer $manufacturer): ?string
    {
        $parts = array_filter([
            self::percent($manufacturer?->purchase_discount_1),
            self::percent($manufacturer?->purchase_discount_2),
        ], fn (float $value) => $value > 0);

        if ($parts === []) {
            return null;
        }

        return implode(' + ', array_map(fn (float $value) => self::formatPercent($value), $parts));
    }

    public static function formatPercent(mixed $value): string
    {
        return rtrim(rtrim(number_format(self::percent($value), 2, ',', '.'), '0'), ',').' %';
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            self::SOURCE_CALCULATED => 'berechnet: Listenpreis − Rabatte',
            self::SOURCE_PRICELIST => 'aus der Preisliste',
            self::SOURCE_MANUAL => 'von Hand (Sonderpreis)',
            default => '—',
        };
    }

    protected static function percent(mixed $value): float
    {
        $value = is_string($value) ? str_replace(',', '.', $value) : $value;

        return is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : 0.0;
    }
}
