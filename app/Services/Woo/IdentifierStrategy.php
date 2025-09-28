<?php

namespace App\Services\Woo;

use Illuminate\Support\Str;

/**
 * IdentifierStrategy
 * Normalisiert SKU/EAN/MPN und liefert Kandidaten.
 */
class IdentifierStrategy
{
    public function normalizeSku(?string $sku): ?string
    {
        if (!$sku) return null;
        $n = trim(Str::upper($sku));
        $n = preg_replace('/[\s_\-\.]/', '', $n);
        return $n ?: null;
    }

    public function normalizeMpn(?string $mpn): ?string
    {
        if (!$mpn) return null;
        return trim(Str::upper($mpn));
    }

    public function normalizeEan(?string $ean): ?string
    {
        if (!$ean) return null;
        $n = preg_replace('/\D/', '', $ean);
        return $this->isValidEan($n) ? $n : null;
    }

    public function isValidEan(string $ean): bool
    {
        $len = strlen($ean);
        if (!in_array($len, [8,12,13,14], true)) return false;
        return ctype_digit($ean);
    }

    public function buildCandidates(?string $localSku, ?string $ean, ?string $mpn, ?string $knownWooSku = null): array
    {
        $c = [];
        if ($n = $this->normalizeSku($localSku))   $c[] = ['type'=>'LOCAL_SKU','value'=>$n,'confidence'=>100];
        if ($knownWooSku && ($w = $this->normalizeSku($knownWooSku))) $c[] = ['type'=>'WOO_SKU','value'=>$w,'confidence'=>100];
        if ($n = $this->normalizeEan($ean))        $c[] = ['type'=>'EAN','value'=>$n,'confidence'=>90];
        if ($n = $this->normalizeMpn($mpn))        $c[] = ['type'=>'MPN','value'=>$n,'confidence'=>70];
        return $c;
    }
}
