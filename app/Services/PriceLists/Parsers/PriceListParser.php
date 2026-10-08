<?php

namespace App\Services\PriceLists\Parsers;

use App\Services\PriceLists\PriceListRow;

interface PriceListParser
{
    /**
     * Schlüssel der Liste, z. B. "aliens" (Config config/price_lists.php, Meta-Key).
     */
    public function key(): string;

    /**
     * Nur Produkte dieses Herstellers zuordnen (null = alle Hersteller).
     */
    public function manufacturerName(): ?string;

    /**
     * @param  array<string, list<list<string|null>>>  $sheets  Blattname => Zeilen
     * @return list<PriceListRow>
     */
    public function parse(array $sheets): array;
}
