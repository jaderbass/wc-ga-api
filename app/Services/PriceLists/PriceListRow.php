<?php

namespace App\Services\PriceLists;

/**
 * Eine Artikelzeile aus einer Hersteller-/Händler-Preisliste.
 */
final class PriceListRow
{
    /** Trennzeichen, wenn eine Zeile mehrere Markierungen hat ("DNV + NEU"). */
    public const MARKER_SEPARATOR = ' + ';

    public function __construct(
        public readonly int $line,
        public readonly string $articleNumber,
        public readonly string $text,
        public readonly ?string $brand = null,
        public readonly ?string $sourceCategory = null,
        public readonly ?string $marker = null,
        public readonly ?string $ean = null,
        public readonly ?int $purchasePriceCents = null,
        public readonly ?int $retailPriceCents = null,
        public readonly ?int $weightGrams = null,
        public readonly ?string $unit = null,
        public readonly ?string $customsTariff = null,
        public readonly ?string $countryOfOrigin = null,
        public readonly ?int $listPriceCents = null,
        public readonly ?string $availableFrom = null,
        /** @var list<string> Anwendungsgebiete laut Liste (z. B. Skylotec), siehe config price_lists.<liste>.application_areas */
        public readonly array $applicationAreas = [],
    ) {}

    /**
     * Nicht mehr lieferbar: Aliens "AUSVERKAUFT", Petzl "EOL" (End of Life).
     */
    public function isUnavailable(): bool
    {
        return $this->hasMarker('AUSVERKAUFT') || $this->hasMarker('EOL');
    }

    /**
     * Neu im Sortiment (Petzl "NEW"), ggf. erst ab {@see $availableFrom} lieferbar.
     */
    public function isNew(): bool
    {
        return $this->hasMarker('NEW');
    }

    public function isClearance(): bool
    {
        return $this->hasMarker('ABVERKAUF');
    }

    /**
     * Beginnt eine der Markierungen mit $prefix ("AUSVERKAUFT 25/03" → "AUSVERKAUFT")?
     */
    public function hasMarker(string $prefix): bool
    {
        if ($this->marker === null) {
            return false;
        }

        foreach (explode(self::MARKER_SEPARATOR, $this->marker) as $marker) {
            if (str_starts_with($marker, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Daten, die als Produkt-Meta gespeichert werden.
     *
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        return array_filter([
            'article_number' => $this->articleNumber,
            'text' => $this->text,
            'brand' => $this->brand,
            'source_category' => $this->sourceCategory,
            'marker' => $this->marker,
            'ean' => $this->ean,
            'purchase_price_cents' => $this->purchasePriceCents,
            'retail_price_cents' => $this->retailPriceCents,
            'list_price_cents' => $this->listPriceCents,
            'available_from' => $this->availableFrom,
            'application_areas' => $this->applicationAreas !== [] ? implode(' | ', $this->applicationAreas) : null,
            'weight_g' => $this->weightGrams,
            'unit' => $this->unit,
            'customs_tariff' => $this->customsTariff,
            'country_of_origin' => $this->countryOfOrigin,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
