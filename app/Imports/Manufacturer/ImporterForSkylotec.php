<?php

namespace App\Imports\Manufacturer;

use App\Importers\Contracts\CsvImporterContract;
use App\Importers\Contracts\HandlesUploadedFile;
use App\Importers\GenericCsvProductImporter;
use App\Support\ImportLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Hersteller-Importer für Kask (CSV).
 *
 * Verwendet das Kask-spezifische Mapping aus
 * config/import_mappings/kask.php.
 */
class ImporterForSkylotec extends GenericCsvProductImporter implements CsvImporterContract, HandlesUploadedFile
{
    protected ?int $manufacturerId = null;

    protected array $mapping = [];

    public function __construct(int $manufacturerId)
    {
        parent::__construct(
            mappingFile: 'skylotec',
            manufacturerId: $manufacturerId
        );

        $this->mapping = config('import_mappings.skylotec', []);
    }

    /**
     * Verarbeitet eine hochgeladene Kask-CSV-Datei.
     */
    public function handleUploadedFile(UploadedFile $file): void
    {
        ImportLog::debug('ImporterForSkylotec.handleUploadedFile ENTER', [
            'mapping_keys' => array_keys($this->mapping),
        ]);

        $filename = uniqid('skylotec_', true).'.csv';
        $stored = $file->storeAs('imports', $filename);

        Log::info('Import gestartet', [
            'class' => static::class,
            'manufacturer_id' => (string) $this->manufacturerId,
            'sourceType' => 'csv',
            'source' => $stored,
        ]);

        $this->import(Storage::path($stored));
    }

    /**
     * CSV-Import über einen absoluten Pfad.
     */
    public function import(string $filePath): void
    {
        // Skylotec-Mapping unmittelbar vor dem Import nochmals erzwingen.
        $this->mapping = config('import_mappings.skylotec', []);

        ImportLog::debug('ImporterForSkylotec import mapping', [
            'product_map' => $this->mapping['product'] ?? null,
            'variation_map' => $this->mapping['variation'] ?? null,
            'var_fields_map' => $this->mapping['variation_fields'] ?? null,
        ]);

        parent::import($filePath);
    }

    /**
     * Importiert eine Skylotec-Produktgruppe als simples oder variables Produkt.
     *
     * Einzelne Zeilen werden als simples Produkt behandelt. Enthält eine Gruppe
     * mehrere Zeilen, werden alle Zeilen als Varianten desselben Hauptprodukts
     * verarbeitet.
     *
     * @param  string  $groupKey  Gruppenschlüssel des Skylotec-Produkts.
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    protected function importProductGroup(
        string $groupKey,
        \Illuminate\Support\Collection $rows
    ): void {
        $originalFilter = $this->mapping['variation_row_filter'] ?? null;
        $originalVariationMapping = $this->mapping['variation'] ?? [];

        $this->mapping['variation'] =
            SkylotecVariationAttributeResolver::resolve($rows);

        /*
        * Einzelne Zeile = simples Produkt.
        * Mehrere Zeilen mit demselben sicheren Group-Key =
        * alle Zeilen sind Varianten eines synthetischen Parents.
        */
        $this->mapping['variation_row_filter'] = $rows->count() > 1
            ? fn (array $row): bool => true
            : fn (array $row): bool => false;

        try {
            parent::importProductGroup($groupKey, $rows);
        } finally {
            if ($originalFilter !== null) {
                $this->mapping['variation_row_filter'] = $originalFilter;
            } else {
                unset($this->mapping['variation_row_filter']);
            }
        }
    }

    /**
     * Bereitet Skylotec-Variantenwerte vor und übergibt die Variante
     * anschließend an den generischen CSV-Importer.
     */
    protected function importVariation(
        \App\Models\Product $product,
        array $row
    ) {
        if (array_key_exists('Seillänge', $this->mapping['variation'] ?? [])) {
            $row['SKYLOTEC_SEILLAENGE'] =
                SkylotecVariationAttributeResolver::formatValue(
                    'Seillänge',
                    $row['Seillänge'] ?? null,
                    $row
                );
        }

        if (
            array_key_exists(
                'Länge Verbindungsmittel',
                $this->mapping['variation'] ?? []
            )
        ) {
            $row['SKYLOTEC_LAENGE_VERBINDUNGSMITTEL'] =
                SkylotecVariationAttributeResolver::formatValue(
                    'Länge Verbindungsmittel',
                    $row['Länge Verbindungsmittel'] ?? null,
                    $row
                );
        }

        return parent::importVariation($product, $row);
    }

    /**
     * Bereitet ein Skylotec-Hauptprodukt vor dem Upsert auf.
     *
     * Bei variablen Produkten wird der Gruppenschlüssel als Parent-Artikelnummer
     * und Parent-SKU verwendet. Variantenspezifische Daten wie EAN, Preis und
     * Gewicht werden nicht auf das Hauptprodukt übernommen.
     *
     * Der Wert für `online_sellable` wird nur übernommen, wenn er innerhalb
     * der gesamten Produktgruppe konsistent ist. Bei widersprüchlichen Werten
     * wird `online_sellable` am Parent auf null gesetzt und eine Warnung geloggt.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $productPayload
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $variationRows
     * @return array<string, mixed>
     */
    protected function beforeProductUpsert(
        string $groupKey,
        \Illuminate\Support\Collection $rows,
        array $productPayload,
        \Illuminate\Support\Collection $variationRows
    ): array {
        /*
        * Variantenattribute gruppenbezogen bestimmen.
        *
        * Nur Attribute, die innerhalb dieser Produktgruppe tatsächlich
        * unterschiedliche Werte besitzen, werden als Variantenattribute
        * verwendet.
        */
        $variationMapping = SkylotecVariationAttributeResolver::resolve($rows);

        if (array_key_exists('Seillänge', $variationMapping)) {
            $variationMapping['Seillänge'] = 'SKYLOTEC_SEILLAENGE';
        }

        if (array_key_exists('Länge Verbindungsmittel', $variationMapping)) {
            $variationMapping['Länge Verbindungsmittel'] =
                'SKYLOTEC_LAENGE_VERBINDUNGSMITTEL';
        }

        $this->mapping['variation'] = $variationMapping;

        ImportLog::debug('Skylotec variation attributes resolved', [
            'group' => $groupKey,
            'mapping' => $variationMapping,
        ]);

        if ($variationRows->count() <= 1) {
            return $productPayload;
        }

        $productPayload['product_number'] = $groupKey;
        $productPayload['sku'] = $groupKey;

        $productPayload['ean'] = null;
        $productPayload['manufacturer_price_cents'] = null;
        $productPayload['weight_g'] = 0;

        $onlineSellableValues = $rows
            ->map(fn (array $row) => trim((string) ($row['Online verkaufbar'] ?? '')))
            ->filter(fn (string $value) => $value !== '')
            ->map(function (string $value) {
                return filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            })
            ->filter(fn ($value) => $value !== null)
            ->unique()
            ->values();

        if ($onlineSellableValues->count() === 1) {
            $productPayload['online_sellable'] =
                $onlineSellableValues->first() ? 1 : 0;
        }

        if ($onlineSellableValues->count() > 1) {
            \Log::warning('Inconsistent Skylotec online_sellable values', [
                'group' => $groupKey,
                'values' => $onlineSellableValues->all(),
            ]);

            $productPayload['online_sellable'] = null;
        }

        return $productPayload;
    }
}
