<?php

namespace App\Services\PriceLists;

/**
 * Ergebnis von {@see PriceListApplier::apply()}.
 */
final class PriceListReport
{
    public int $rows = 0;

    public int $products = 0;

    /** @var array<string, int> Zuordnungsweg => Anzahl Zeilen */
    public array $matchedBy = [];

    /** @var list<PriceListRow> */
    public array $unmatched = [];

    /** @var list<PriceListRow> */
    public array $ambiguous = [];

    public int $soldOutProducts = 0;

    public int $clearanceProducts = 0;

    public int $newProducts = 0;

    /** @var array<string, array{manufacturer_id: int, source: string, status: string, result: string, products: int}> */
    public array $sources = [];

    public int $keywordRulesCreated = 0;

    /** @var array<string, true> */
    public array $missingCategories = [];

    public function __construct(
        public readonly string $list,
        public readonly bool $dryRun,
    ) {}

    public function matchedRows(): int
    {
        return array_sum($this->matchedBy);
    }

    /**
     * @return array<string, int> Status (vorschlag/offen/vorhanden) => Anzahl Herstellerkategorien
     */
    public function sourceStatusCounts(): array
    {
        $counts = [];

        foreach ($this->sources as $source) {
            $counts[$source['status']] = ($counts[$source['status']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, int> Marke => Anzahl nicht zugeordneter Zeilen
     */
    public function unmatchedByBrand(): array
    {
        $counts = [];

        foreach ($this->unmatched as $row) {
            $brand = $row->brand ?? '(ohne Marke)';
            $counts[$brand] = ($counts[$brand] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
