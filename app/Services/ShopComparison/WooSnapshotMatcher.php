<?php

namespace App\Services\ShopComparison;

use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use Illuminate\Support\Facades\DB;

/**
 * Ordnet die Einträge einer Shop-Momentaufnahme den Produkten und Varianten der Datenbank zu.
 *
 * Reihenfolge: SKU (Varianten-SKU vor Produkt-SKU/Artikelnummer), danach EAN als Ersatz.
 * Variable Elternprodukte werden über ihre Varianten zugeordnet. Verändert nur die Abgleich-Spalten
 * der Momentaufnahme, keine Produktdaten.
 */
final class WooSnapshotMatcher
{
    /** @var array{vsku: array<string, array<int, array{0:int,1:int}>>, psku: array<string, array<int,int>>, vean: array<string, array<int, array{0:int,1:int}>>, pean: array<string, array<int,int>>} */
    private array $maps;

    /**
     * @return array<string,int> Anzahl je Abgleich-Status
     */
    public function match(WooSnapshot $snapshot): array
    {
        $this->maps = $this->buildMaps();

        DB::transaction(function () use ($snapshot) {
            $this->matchUnits($snapshot);
            $this->matchParents($snapshot);
            $snapshot->update(['matched_at' => now()]);
        });

        return WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->selectRaw('match_status, COUNT(*) as aggregate')
            ->groupBy('match_status')
            ->pluck('aggregate', 'match_status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function matchUnits(WooSnapshot $snapshot): void
    {
        WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->where('type', '!=', WooSnapshotItem::TYPE_VARIABLE)
            ->select(['id', 'sku_key', 'ean_key'])
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    $this->save($item->id, $this->resolve($item->sku_key, $item->ean_key, true));
                }
            });
    }

    private function matchParents(WooSnapshot $snapshot): void
    {
        $childProducts = WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->where('type', WooSnapshotItem::TYPE_VARIATION)
            ->whereNotNull('matched_product_id')
            ->get(['woo_parent_id', 'matched_product_id'])
            ->groupBy('woo_parent_id')
            ->map(fn ($rows) => $rows->countBy('matched_product_id')->sortDesc());

        $hasChildren = WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->where('type', WooSnapshotItem::TYPE_VARIATION)
            ->distinct()
            ->pluck('woo_parent_id')
            ->flip();

        WooSnapshotItem::query()
            ->where('woo_snapshot_id', $snapshot->id)
            ->where('type', WooSnapshotItem::TYPE_VARIABLE)
            ->select(['id', 'woo_id', 'sku_key', 'ean_key'])
            ->chunkById(500, function ($parents) use ($childProducts, $hasChildren) {
                foreach ($parents as $parent) {
                    $own = $this->resolve($parent->sku_key, $parent->ean_key, false);

                    if (in_array($own['status'], [WooSnapshotItem::MATCH_SKU, WooSnapshotItem::MATCH_EAN], true)) {
                        $this->save($parent->id, $own);

                        continue;
                    }

                    $counts = $childProducts->get($parent->woo_id);

                    if ($counts && $counts->isNotEmpty()) {
                        $this->save($parent->id, [
                            'status' => $counts->count() === 1 ? WooSnapshotItem::MATCH_VIA_VARIATIONS : WooSnapshotItem::MATCH_AMBIGUOUS,
                            'method' => WooSnapshotItem::MATCH_VIA_VARIATIONS,
                            'product' => (int) $counts->keys()->first(),
                            'variation' => null,
                            'candidates' => $counts->count(),
                        ]);

                        continue;
                    }

                    $hasKey = $parent->sku_key !== null || $parent->ean_key !== null || $hasChildren->has($parent->woo_id);
                    $this->save($parent->id, $this->none($hasKey));
                }
            });
    }

    /**
     * @return array{status:string, method:?string, product:?int, variation:?int, candidates:int}
     */
    private function resolve(?string $skuKey, ?string $eanKey, bool $useVariations): array
    {
        if ($skuKey !== null) {
            $found = $this->pick(
                $useVariations ? ($this->maps['vsku'][$skuKey] ?? []) : [],
                $this->maps['psku'][$skuKey] ?? [],
                WooSnapshotItem::MATCH_SKU
            );

            if ($found) {
                return $found;
            }
        }

        if ($eanKey !== null) {
            $found = $this->pick(
                $useVariations ? ($this->maps['vean'][$eanKey] ?? []) : [],
                $this->maps['pean'][$eanKey] ?? [],
                WooSnapshotItem::MATCH_EAN
            );

            if ($found) {
                return $found;
            }
        }

        return $this->none($skuKey !== null || $eanKey !== null);
    }

    /**
     * Varianten-Treffer haben Vorrang vor Produkt-Treffern (Artikelnummer am Elternprodukt).
     *
     * @param  array<int, array{0:int,1:int}>  $variationHits  [product_id, variation_id]
     * @param  array<int,int>  $productHits
     */
    private function pick(array $variationHits, array $productHits, string $method): ?array
    {
        if ($variationHits !== []) {
            $byVariation = [];
            foreach ($variationHits as [$productId, $variationId]) {
                $byVariation[$variationId] = $productId;
            }

            $products = array_values(array_unique($byVariation));
            $single = count($byVariation) === 1;

            return [
                'status' => $single ? $method : WooSnapshotItem::MATCH_AMBIGUOUS,
                'method' => $method,
                'product' => count($products) === 1 ? $products[0] : null,
                'variation' => $single ? array_key_first($byVariation) : null,
                'candidates' => count($byVariation),
            ];
        }

        $productHits = array_values(array_unique($productHits));

        if ($productHits === []) {
            return null;
        }

        $single = count($productHits) === 1;

        return [
            'status' => $single ? $method : WooSnapshotItem::MATCH_AMBIGUOUS,
            'method' => $method,
            'product' => $single ? $productHits[0] : null,
            'variation' => null,
            'candidates' => count($productHits),
        ];
    }

    private function none(bool $hasKey): array
    {
        return [
            'status' => $hasKey ? WooSnapshotItem::MATCH_SHOP_ONLY : WooSnapshotItem::MATCH_NO_KEY,
            'method' => null,
            'product' => null,
            'variation' => null,
            'candidates' => 0,
        ];
    }

    private function save(int $id, array $result): void
    {
        DB::table('woo_snapshot_items')->where('id', $id)->update([
            'match_status' => $result['status'],
            'match_method' => $result['method'],
            'matched_product_id' => $result['product'],
            'matched_variation_id' => $result['variation'],
            'candidate_count' => $result['candidates'],
        ]);
    }

    private function buildMaps(): array
    {
        $maps = ['vsku' => [], 'psku' => [], 'vean' => [], 'pean' => []];

        foreach (DB::table('products')->select(['id', 'sku', 'product_number', 'ean'])->cursor() as $row) {
            foreach ([$row->sku, $row->product_number] as $value) {
                if ($key = ComparisonKey::sku($value)) {
                    $maps['psku'][$key][] = (int) $row->id;
                }
            }

            if ($key = ComparisonKey::ean($row->ean)) {
                $maps['pean'][$key][] = (int) $row->id;
            }
        }

        foreach (DB::table('product_variations')->select(['id', 'product_id', 'sku', 'ean'])->cursor() as $row) {
            if ($key = ComparisonKey::sku($row->sku)) {
                $maps['vsku'][$key][] = [(int) $row->product_id, (int) $row->id];
            }

            if ($key = ComparisonKey::ean($row->ean)) {
                $maps['vean'][$key][] = [(int) $row->product_id, (int) $row->id];
            }
        }

        return $maps;
    }
}
