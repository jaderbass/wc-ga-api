<?php

namespace App\Services\Woo;

use App\Models\WooLink;
use Illuminate\Support\Arr;

/**
 * Repository für WooLink.
 */
class WooLinkStore
{
    public function findByLocalSku(int $shopId, string $localSku): ?WooLink
    {
        return WooLink::query()
            ->where('shop_id',$shopId)
            ->where('local_sku',$localSku)
            ->first();
    }

    public function findByWooSku(int $shopId, string $wooSku): ?WooLink
    {
        return WooLink::query()
            ->where('shop_id',$shopId)
            ->where('woo_sku',$wooSku)
            ->first();
    }

    public function findByMeta(int $shopId, string $type, string $value): ?WooLink
    {
        $col = strtolower($type) === 'ean' ? 'ean' : 'mpn';
        return WooLink::query()
            ->where('shop_id',$shopId)
            ->where($col,$value)
            ->first();
    }

    public function upsert(array $payload): WooLink
    {
        $shopId = Arr::get($payload, 'shop_id');
        $wooSku = Arr::get($payload, 'woo_sku');
        $link = $wooSku ? $this->findByWooSku($shopId, $wooSku) : null;

        if (!$link) $link = new WooLink();
        $link->fill($payload);
        $link->save();

        return $link;
    }

    public function upsertFromResolved(int $shopId, ?string $skuKey, ResolvedTarget $rt, array $meta = []): WooLink
    {
        return $this->upsert([
            'shop_id'          => $shopId,
            'local_sku'        => $skuKey,
            'woo_sku'          => $meta['woo_sku'] ?? null,
            'woo_product_id'   => $rt->woo_product_id,
            'woo_variation_id' => $rt->woo_variation_id,
            'ean'              => $meta['ean'] ?? null,
            'mpn'              => $meta['mpn'] ?? null,
            'protect_sku'      => true,
            'confidence'       => $rt->confidence,
        ]);
    }
}
