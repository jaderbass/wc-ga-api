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
class ImporterForKask extends GenericCsvProductImporter implements CsvImporterContract, HandlesUploadedFile
{
    protected ?int $manufacturerId = null;

    protected array $mapping = [];

    public function __construct(int $manufacturerId)
    {
        parent::__construct(
            mappingFile: 'kask',
            manufacturerId: $manufacturerId
        );

        $this->mapping = config('import_mappings.kask', []);
    }

    /**
     * Verarbeitet eine hochgeladene Kask-CSV-Datei.
     */
    public function handleUploadedFile(UploadedFile $file): void
    {
        ImportLog::debug('ImporterForKask.handleUploadedFile ENTER', [
            'mapping_keys' => array_keys($this->mapping),
        ]);

        $filename = uniqid('kask_', true) . '.csv';
        $stored = $file->storeAs('imports', $filename);

        Log::info('Import gestartet', [
            'class'           => static::class,
            'manufacturer_id' => (string) $this->manufacturerId,
            'sourceType'      => 'csv',
            'source'          => $stored,
        ]);

        $this->import(Storage::path($stored));
    }

    /**
     * CSV-Import über einen absoluten Pfad.
     */
    public function import(string $filePath): void
    {
        // Kask-Mapping unmittelbar vor dem Import nochmals erzwingen.
        $this->mapping = config('import_mappings.kask', []);

        ImportLog::debug('ImporterForKask import mapping', [
            'product_map'    => $this->mapping['product'] ?? null,
            'variation_map'  => $this->mapping['variation'] ?? null,
            'var_fields_map' => $this->mapping['variation_fields'] ?? null,
        ]);

        parent::import($filePath);
    }

    /**
     * Ergänzt Kask-spezifische Varianteninformationen aus DESCRIPTION
     * und übergibt die Zeile anschließend an den Generic Importer.
     */
    protected function importVariation(
        \App\Models\Product $product,
        array $row
    ) {
        $description = trim((string) ($row['DESCRIPTION'] ?? ''));

        if ($description !== '') {
            $parts = array_map('trim', explode(',', $description));

            /*
            * Erwartetes Format:
            *
            * Produktname, 202-Yellow, Tg
            * Produktname, 240-Black/White, 00
            */
            $colorPart = $parts[1] ?? null;
            $sizePart  = $parts[2] ?? null;

            if ($colorPart !== null && $colorPart !== '') {
                if (preg_match('/^(\d+)-(.*)$/', $colorPart, $matches)) {
                    $row['KASK_COLOR_CODE'] = trim($matches[1]);
                    $row['KASK_COLOR']      = trim($matches[2]);
                } else {
                    $row['KASK_COLOR'] = $colorPart;
                }
            }

            if ($sizePart !== null && $sizePart !== '') {
                $row['KASK_SIZE'] = $sizePart;
            }
        }

        return parent::importVariation($product, $row);
    }

    /**
     * Bereitet Kask-Hauptprodukte vor dem Upsert auf.
     *
     * Regeln:
     * - vorhandene Basiszeile (z. B. WAC00001-) bevorzugen
     * - wenn keine Basiszeile existiert, Parent-SKU aus dem Gruppenschlüssel bilden
     * - Variantenanteile aus der Beschreibung entfernen
     * - variantenspezifische EAN am Parent nicht übernehmen
     */
    protected function beforeProductUpsert(
        string $groupKey,
        \Illuminate\Support\Collection $rows,
        array $productPayload,
        \Illuminate\Support\Collection $variationRows
    ): array {
        $baseRow = $rows->first(function (array $row): bool {
            $partNumber = trim((string) ($row['PART #'] ?? ''));

            return $partNumber !== ''
                && preg_match('/-$/', $partNumber) === 1;
        });

        if ($baseRow !== null) {
            $partNumber = trim((string) ($baseRow['PART #'] ?? ''));

            if ($partNumber !== '') {
                $productPayload['product_number'] = $partNumber;
            }

            $description = trim((string) ($baseRow['DESCRIPTION'] ?? ''));

            if ($description !== '') {
                $productPayload['product_name'] = $description;
                $productPayload['original_product_name'] = $description;
                $productPayload['description'] = $description;
            }

            $ean = trim((string) ($baseRow['EAN CODE'] ?? ''));

            $productPayload['ean'] = $ean !== ''
                ? $ean
                : null;

            return $productPayload;
        }

        /*
        * Keine Basiszeile vorhanden:
        * Parent aus Gruppenschlüssel und erster Variantenzeile ableiten.
        */
        $productPayload['product_number'] = $groupKey . '-';

        $firstRow = $rows->first();

        $description = trim((string) ($firstRow['DESCRIPTION'] ?? ''));

        if ($description !== '') {
            /*
            * Typisches Kask-Format:
            *
            * "SUN SHIELD HI V, 221-Yellow Fluo, Tg"
            * -> "SUN SHIELD HI V"
            */
            $parts = array_map('trim', explode(',', $description));

            $parentName = $parts[0] ?? $description;

            if ($parentName !== '') {
                $productPayload['product_name'] = $parentName;
                $productPayload['original_product_name'] = $parentName;
                $productPayload['description'] = $parentName;
            }
        }

        /*
        * Eine Varianten-EAN und ein Varianten-Gewicht
        * dürfen nicht zum synthetischen Parent übernommen werden.
        */
        $productPayload['ean'] = null;
        $productPayload['weight'] = null;

        return $productPayload;
    }
}