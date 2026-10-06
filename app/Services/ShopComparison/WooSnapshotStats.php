<?php

namespace App\Services\ShopComparison;

use App\Models\WooSnapshot;
use App\Models\WooSnapshotItem;
use Illuminate\Support\Facades\DB;

/**
 * Kennzahlen einer Shop-Momentaufnahme für Befehl und Admin-Seite.
 */
final class WooSnapshotStats
{
    /**
     * @return array{
     *   types: array<string,int>,
     *   units: array<string,int>,
     *   units_total: int,
     *   parents: array<string,int>,
     *   duplicate_skus: int,
     *   db_products: int,
     *   db_products_matched: int,
     *   db_products_only: int
     * }
     */
    public static function for(WooSnapshot $snapshot): array
    {
        $base = fn () => WooSnapshotItem::query()->where('woo_snapshot_id', $snapshot->id);

        $types = self::countBy($base(), 'type');
        $units = self::countBy($base()->where('type', '!=', WooSnapshotItem::TYPE_VARIABLE), 'match_status');
        $parents = self::countBy($base()->where('type', WooSnapshotItem::TYPE_VARIABLE), 'match_status');

        $duplicateSkus = $base()
            ->where('type', '!=', WooSnapshotItem::TYPE_VARIABLE)
            ->whereNotNull('sku_key')
            ->select('sku_key')
            ->groupBy('sku_key')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $dbProducts = DB::table('products')->count();
        $dbMatched = $base()->whereNotNull('matched_product_id')->distinct()->count('matched_product_id');

        return [
            'types' => $types,
            'units' => $units,
            'units_total' => array_sum($units),
            'parents' => $parents,
            'duplicate_skus' => $duplicateSkus,
            'db_products' => $dbProducts,
            'db_products_matched' => $dbMatched,
            'db_products_only' => max(0, $dbProducts - $dbMatched),
        ];
    }

    /** @return array<string,int> */
    private static function countBy($query, string $column): array
    {
        return $query
            ->selectRaw("{$column} as grp, COUNT(*) as aggregate")
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->grp ?? '') => (int) $row->aggregate])
            ->all();
    }
}
