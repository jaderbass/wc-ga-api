<?php

namespace App\Services\Aliens;

use App\Models\Product;
use App\Models\ProductMeta;
use App\Models\ProductVariation;
use RuntimeException;

/**
 * Produktdaten aus dem Aliens-B2B-Bereich ("Aliens-Produkte.xlsx/.csv",
 * https://b2b.aliens-outdoor.com/shopprodukte/), eine Zeile je Variante.
 *
 * - gruppiert nach "Produkt ID" (= aliens_product_id unserer Aliens-Produkte)
 * - erkennt, welche Produkte es bei uns schon gibt (Aliens-ID, Artikelnummer, EAN)
 * - übersetzt fehlende Produkte in das Format des bisherigen Aliens-Exports,
 *   damit sie über {@see \App\Importers\AliensCsvStreamImporter} angelegt werden
 */
class AliensB2bProductData
{
    /** Spalten der B2B-Datei, die wir brauchen. */
    protected const REQUIRED = ['Produkt ID', 'Produktname', 'Kombinationsreferenz', 'Produktreferenz'];

    /**
     * @param  array<int, array<int, mixed>>  $rows  Zeilen inkl. Kopfzeile
     * @return array<string, list<array<string, string>>> Produkt ID => Variantenzeilen
     */
    public static function group(array $rows): array
    {
        $header = array_map(fn ($value) => trim((string) $value), array_shift($rows) ?? []);

        foreach (self::REQUIRED as $column) {
            if (! in_array($column, $header, true)) {
                throw new RuntimeException("Aliens-B2B-Produktdaten: Spalte \"{$column}\" fehlt.");
            }
        }

        $groups = [];

        foreach ($rows as $row) {
            $assoc = [];

            foreach ($header as $index => $column) {
                $assoc[$column] = trim((string) ($row[$index] ?? ''));
            }

            if ($assoc['Produkt ID'] === '' || ($assoc['Aktiv'] ?? '1') === '0') {
                continue;
            }

            $groups[$assoc['Produkt ID']][] = $assoc;
        }

        return $groups;
    }

    /**
     * Welche Produkte gibt es schon? Gesucht wird über die Aliens-Produkt-ID,
     * die Artikelnummern (Variante/Produkt) und die EANs.
     *
     * @param  array<string, list<array<string, string>>>  $groups
     * @return array<string, string> Produkt ID => Grund ("Aliens-ID", "Artikelnummer", "EAN")
     */
    public static function existing(array $groups): array
    {
        $found = [];

        foreach (array_chunk(array_keys($groups), 500) as $ids) {
            $known = ProductMeta::query()
                ->where('key', 'aliens_product_id')
                ->whereIn('value', array_map('strval', $ids))
                ->pluck('value');

            foreach ($known as $id) {
                $found[(string) $id] = 'Aliens-ID';
            }
        }

        $refs = [];
        $refValues = [];
        $eans = [];

        foreach ($groups as $id => $rows) {
            if (isset($found[$id])) {
                continue;
            }

            foreach ($rows as $row) {
                foreach ([$row['Kombinationsreferenz'], $row['Produktreferenz']] as $ref) {
                    if ($ref !== '') {
                        $refs[mb_strtolower($ref)][] = $id;
                        $refValues[$ref] = true;
                    }
                }

                foreach ([$row['Kombination EAN-13'] ?? '', $row['EAN-13'] ?? ''] as $ean) {
                    if (preg_match('/^\d{8,14}$/', $ean) === 1) {
                        $eans[$ean][] = $id;
                    }
                }
            }
        }

        // in Originalschreibweise abfragen; MariaDB vergleicht ohnehin ohne Groß-/Kleinschreibung
        foreach (array_chunk(array_map('strval', array_keys($refValues)), 500) as $chunk) {
            $hits = ProductVariation::query()->whereIn('sku', $chunk)->pluck('sku')
                ->merge(Product::query()->whereIn('sku', $chunk)->pluck('sku'))
                ->merge(Product::query()->whereIn('product_number', $chunk)->pluck('product_number'));

            foreach ($hits as $hit) {
                foreach ($refs[mb_strtolower((string) $hit)] ?? [] as $id) {
                    $found[$id] ??= 'Artikelnummer';
                }
            }
        }

        foreach (array_chunk(array_map('strval', array_keys($eans)), 500) as $chunk) {
            $hits = ProductVariation::query()->whereIn('ean', $chunk)->pluck('ean')
                ->merge(Product::query()->whereIn('ean', $chunk)->pluck('ean'));

            foreach ($hits as $hit) {
                foreach ($eans[(string) $hit] ?? [] as $id) {
                    $found[$id] ??= 'EAN';
                }
            }
        }

        return $found;
    }

    /**
     * Fehlende Produkte als CSV im Format des bisherigen Aliens-Exports schreiben.
     *
     * Produkte ohne Kombination bekommen eine Variante mit der Produktreferenz als
     * Artikelnummer (Kombinations-ID "P<Produkt ID>"), damit Preislisten sie über
     * die Artikelnummer finden.
     *
     * @param  array<string, list<array<string, string>>>  $groups
     */
    public static function writeLegacyCsv(array $groups, string $path): int
    {
        $base = ['Produkt-ID', 'Produktname', 'Hersteller', 'Kombination-ID', 'Kombinations-Referenz', 'EAN-13', 'Kombination EAN13', 'Beschreibung', 'Produkt-URL', 'Produktbilder', 'Kombination Bilder', 'Kategorie'];
        $attributeColumns = [];
        $featureColumns = [];
        $lines = [];

        foreach ($groups as $id => $rows) {
            foreach ($rows as $index => $row) {
                $attributes = self::pairs($row['Kombination (Attribute)'] ?? '');
                $features = $index === 0 ? self::pairs($row['Merkmale'] ?? '') : [];
                $simple = ($row['Kombinations-ID'] ?? '') === '';

                $line = [
                    'Produkt-ID' => $id,
                    'Produktname' => $row['Produktname'],
                    'Hersteller' => $row['Herstellername'] ?? '',
                    'Kombination-ID' => $simple ? 'P'.$id : $row['Kombinations-ID'],
                    'Kombinations-Referenz' => $row['Kombinationsreferenz'] !== '' ? $row['Kombinationsreferenz'] : $row['Produktreferenz'],
                    'EAN-13' => $row['EAN-13'] ?? '',
                    'Kombination EAN13' => ($row['Kombination EAN-13'] ?? '') !== '' ? $row['Kombination EAN-13'] : ($simple ? ($row['EAN-13'] ?? '') : ''),
                    'Beschreibung' => $row['Lange Beschreibung'] ?? '',
                    'Produkt-URL' => $row['Produkt-Link'] ?? '',
                    'Produktbilder' => $row['Produkt Alle Bild-Links'] ?? '',
                    'Kombination Bilder' => $row['Kombinations-Bild-Links'] ?? '',
                    'Kategorie' => $row['Name der Standardkategorie'] ?? '',
                ];

                foreach ($attributes as $name => $value) {
                    $column = 'Attribute Group: '.$name;
                    $attributeColumns[$column] = true;
                    $line[$column] = $value;
                }

                foreach ($features as $name => $value) {
                    $column = 'Feature: '.$name;
                    $featureColumns[$column] = true;
                    $line[$column] = $value;
                }

                $lines[] = $line;
            }
        }

        $header = array_merge($base, array_keys($attributeColumns), array_keys($featureColumns));
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Kann {$path} nicht schreiben.");
        }

        fputcsv($handle, $header, ';', '"', '');

        foreach ($lines as $line) {
            fputcsv($handle, array_map(fn (string $column): string => (string) ($line[$column] ?? ''), $header), ';', '"', '');
        }

        fclose($handle);

        return count($lines);
    }

    /**
     * "Größe: 18/48,Durchmesser [mm]: Für Ø 8,1 mm bis Ø 11 mm" → [Größe => 18/48, Durchmesser [mm] => …]
     * Ein Komma trennt nur, wenn danach "Name: " folgt (Werte enthalten selbst Kommas).
     *
     * @return array<string, string>
     */
    public static function pairs(string $text): array
    {
        $result = [];

        foreach (preg_split('/,(?=[^,:]+:\s)/u', $text) ?: [] as $part) {
            [$name, $value] = array_pad(explode(':', $part, 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if ($name !== '' && $value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
