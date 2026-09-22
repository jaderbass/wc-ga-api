<?php

namespace App\Imports\Manufacturer;

use Illuminate\Support\Collection;

class SkylotecVariationAttributeResolver
{
    /**
     * Ermittelt die für eine Skylotec-Produktgruppe relevanten
     * Variantenattribute.
     *
     * Ein Attribut wird nur berücksichtigt, wenn innerhalb der Gruppe
     * mindestens zwei unterschiedliche nicht-leere Werte vorkommen.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    public static function resolve(Collection $rows): array
    {
        $candidates = [
            'Seillänge',
            'Größe',
            'Kleidergröße',
            'Farbe',
        ];

        $mapping = [];

        foreach ($candidates as $field) {
            if (self::hasDifferentValues($rows, $field)) {
                $mapping[$field] = $field;
            }
        }

        return $mapping;
    }

    /**
     * Prüft, ob ein Feld innerhalb einer Produktgruppe unterschiedliche
     * nicht-leere Werte enthält.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private static function hasDifferentValues(
        Collection $rows,
        string $field
    ): bool {
        return $rows
            ->pluck($field)
            ->map(fn ($value) => self::normalizeAttributeValue(
                $field,
                (string) $value
            ))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->count() > 1;
    }

    /**
     * Normalisiert einen Attributwert für den Vergleich innerhalb
     * einer Produktgruppe.
     */
    private static function normalizeAttributeValue(
        string $field,
        string $value
    ): string {
        $value = trim($value);

        if ($field !== 'Farbe' || $value === '') {
            return $value;
        }

        $colors = array_filter(
            array_map(
                'trim',
                explode('|', $value)
            )
        );

        $colors = array_values(array_unique($colors));

        sort($colors, SORT_NATURAL | SORT_FLAG_CASE);

        return implode('|', $colors);
    }

    /**
     * Formatiert einen Skylotec-Attributwert für die Anzeige.
     */
    public static function formatValue(
        string $field,
        mixed $value,
        array $row
    ): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($field === 'Seillänge') {
            return self::formatLength(
                $value,
                $row['Seillänge Einheit'] ?? $row['Seillänge_2'] ?? 'm'
            );
        }

        return $value;
    }

    /**
     * Formatiert eine Längenangabe einheitlich mit Leerzeichen zwischen
     * Wert und Maßeinheit.
     */
    private static function formatLength(
        string $value,
        mixed $unit
    ): string {
        $normalized = str_replace(',', '.', trim($value));

        if (is_numeric($normalized)) {
            $number = rtrim(
                rtrim(
                    number_format((float) $normalized, 6, '.', ''),
                    '0'
                ),
                '.'
            );

            $number = str_replace('.', ',', $number);
        } else {
            $number = trim($value);
        }

        $unit = trim((string) $unit);

        return $unit !== ''
            ? $number.' '.$unit
            : $number;
    }
}
