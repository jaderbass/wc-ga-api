<?php

namespace App\Services\Product;

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
    public function resolve(?string $productName): int
    {
        $name = mb_strtolower(trim((string) $productName));

        if ($name === '') {
            return 1;
        }

        $rules = config('assembly_group.rules', []);

        foreach ($rules as $rule) {
            $needle = mb_strtolower(trim((string) ($rule['contains'] ?? '')));
            $value = $rule['value'] ?? null;

            if ($needle === '' || $value === null) {
                continue;
            }

            if (str_contains($name, $needle)) {
                return (int) $value;
            }
        }

        return 1;
    }
}
