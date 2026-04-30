<?php

namespace App\Services\Product;

use App\Models\AssemblyGroupRule;
use App\Models\Product;

/**
 * Resolver zur Ermittlung der Baugruppe eines Produkts anhand seines Namens.
 *
 * Die Baugruppe wird über konfigurierbare, regelbasierte String-Prüfungen
 * (case-insensitive) aus dem final berechneten Produktnamen abgeleitet.
 *
 * Die Reihenfolge der Regeln ist relevant: Die erste passende Regel gewinnt.
 * Wenn keine Regel zutrifft, wird standardmäßig Baugruppe 1 zurückgegeben.
 */
class AssemblyGroupResolver
{
    /**
     * Ermittelt die Baugruppe anhand des übergebenen Produktnamens.
     *
     * Die Regeln werden aus der Konfiguration geladen und nacheinander geprüft.
     * Wenn keine passende Regel gefunden wird, wird Baugruppe 1 zurückgegeben.
     *
     * @param string|null $productName
     * @return int
     */
    public function resolve(Product $product): int
    {
        // Manuelle Zuweisung schützen
        if ($product->assembly_group_source === 'manual') {
            return (int) $product->assembly_group;
        }

        $rules = AssemblyGroupRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($product)) {
                return (int) $rule->assembly_group;
            }
        }

        // Fallback
        return 1;
    }
}
