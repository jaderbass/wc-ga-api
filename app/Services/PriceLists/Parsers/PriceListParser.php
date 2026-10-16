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
     * @param  list<list<string|null>>  $rows  Zeilen des Tabellenblatts
     * @return list<PriceListRow>
     */
    public function parse(array $rows): array;
}
