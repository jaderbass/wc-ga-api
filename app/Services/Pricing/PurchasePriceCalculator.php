<?php

namespace App\Services\Pricing;

use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductVariation;

/**
 * Berechnet den Einkaufspreis (EK) aus Listenpreis und den zwei Rabattstufen
 * des Herstellers. Die Rabatte werden nacheinander angewendet:
 *
 *   EK = Listenpreis × (1 − Rabatt 1) × (1 − Rabatt 2)
 *
 * Petzl: 73,50 € × 0,65 × 0,95 = 45,39 € (nicht 40 % auf einmal).
 * Gerundet wird erst am Ende auf ganze Cent.
 */
class PurchasePriceCalculator
{
    public const SOURCE_CALCULATED = 'calculated';

    public const SOURCE_PRICELIST = 'pricelist';

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
     * Berechnet den EK aller Produkte und Varianten eines Herstellers neu,
     * deren EK aus dem Listenpreis berechnet wird (nicht die mit EK aus der Liste).
     *
     * @return int Anzahl geänderter Datensätze
     */
    public function recalculateForManufacturer(Manufacturer $manufacturer): int
    {
        $changed = 0;
        $productIds = Product::query()->where('manufacturer_id', $manufacturer->id)->select('id');

        $queries = [
            Product::query()->where('manufacturer_id', $manufacturer->id),
            ProductVariation::query()->whereIn('product_id', $productIds),
        ];

        foreach ($queries as $query) {
            $query
                ->whereNotNull('list_price_cents')
                ->where(fn ($q) => $q->whereNull('purchase_price_source')->orWhere('purchase_price_source', self::SOURCE_CALCULATED))
                ->select(['id', 'list_price_cents', 'purchase_price_cents', 'purchase_price_source'])
                ->chunkById(500, function ($records) use ($manufacturer, &$changed): void {
                    foreach ($records as $record) {
                        $purchase = self::calculate($record->list_price_cents, $manufacturer->purchase_discount_1, $manufacturer->purchase_discount_2);

                        if ($record->purchase_price_cents !== $purchase || $record->purchase_price_source !== self::SOURCE_CALCULATED) {
                            $record->forceFill([
                                'purchase_price_cents' => $purchase,
                                'purchase_price_source' => self::SOURCE_CALCULATED,
                            ])->saveQuietly();
                            $changed++;
                        }
                    }
                });
        }

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

        return implode(' + ', array_map(
            fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').' %',
            $parts,
        ));
    }

    protected static function percent(mixed $value): float
    {
        $value = is_string($value) ? str_replace(',', '.', $value) : $value;

        return is_numeric($value) ? max(0.0, min(100.0, (float) $value)) : 0.0;
    }
}
