<?php

namespace App\Console\Commands;

use App\Models\ProductVariation;
use Illuminate\Console\Command;

class FindBadUnitSpacingCommand extends Command
{
    protected $signature = 'products:find-bad-unit-spacing
                            {--limit=100 : Maximale Anzahl Treffer}
                            {--unit= : Optional nur nach einer bestimmten Einheit filtern, z. B. mm oder kN}';

    protected $description = 'Findet Variationen mit Attributwerten ohne Leerzeichen zwischen Zahl und Einheit.';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $unit = $this->option('unit');

        $regex = $this->buildRegex($unit);
        $likeNeedle = $unit ? '%' . $unit . '%' : '%';

        $variations = ProductVariation::query()
            ->with('product:id,product_name')
            ->where('attributes_json', 'like', $likeNeedle)
            ->limit($limit * 10)
            ->get(['id', 'product_id', 'attributes_json']);

        $hits = [];

        foreach ($variations as $variation) {
            $matches = $this->findMatchesInArray($variation->attributes_json, $regex);

            if ($matches === []) {
                continue;
            }

            $hits[] = [
                'variation_id' => $variation->id,
                'product_id' => $variation->product_id,
                'product_name' => $variation->product?->product_name,
                'matches' => $matches,
            ];

            if (count($hits) >= $limit) {
                break;
            }
        }

        if ($hits === []) {
            $this->info('Keine Treffer gefunden.');
            return self::SUCCESS;
        }

        foreach ($hits as $hit) {
            $this->line('---');
            $this->line('Produkt: ' . ($hit['product_name'] ?? '[ohne Namen]'));
            $this->line('Produkt-ID: ' . $hit['product_id']);
            $this->line('Variation-ID: ' . $hit['variation_id']);
            $this->line('Treffer: ' . implode(', ', $hit['matches']));
        }

        $this->newLine();
        $this->info('Gefundene Variationen: ' . count($hits));

        return self::SUCCESS;
    }

    /**
     * Baut die Regex für problematische Werte ohne Leerzeichen.
     */
    private function buildRegex(?string $unit = null): string
    {
        if (is_string($unit) && $unit !== '') {
            return '/\d+(?:[.,]\d+)?' . preg_quote($unit, '/') . '\b/u';
        }

        return '/\d+(?:[.,]\d+)?[[:alpha:]°%][[:alpha:]0-9°%\/²³.-]*/u';
    }

    /**
     * Durchsucht ein verschachteltes Array rekursiv nach Treffern.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function findMatchesInArray(mixed $value, string $regex): array
    {
        $hits = [];

        if (is_string($value)) {
            preg_match_all($regex, $value, $matches);

            foreach ($matches[0] ?? [] as $match) {
                $hits[] = $match;
            }

            return array_values(array_unique($hits));
        }

        if (! is_array($value)) {
            return [];
        }

        foreach ($value as $nestedValue) {
            $hits = array_merge($hits, $this->findMatchesInArray($nestedValue, $regex));
        }

        return array_values(array_unique($hits));
    }
}
