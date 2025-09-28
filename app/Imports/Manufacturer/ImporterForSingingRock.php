<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Importer für Singing Rock-Produktdaten.
 *
 * Unterstützt CSV-, XML- und API-Importe:
 * - CSV: Zeilenweise Einlesen und Mapping
 * - XML: Einlesen von Datei-Uploads mit API-kompatibler Struktur
 * - API: Direktabruf des Singing Rock Feeds
 *
 * Beschreibungstexte werden mit HTML übernommen, alle anderen Werte
 * werden in Strings umgewandelt, um eine saubere Speicherung zu gewährleisten.
 */
class ImporterForSingingRock
{
    /**
     * Verarbeitet eine hochgeladene CSV-Datei und speichert Produkte.
     *
     * @param string $filePath Pfad zur hochgeladenen CSV-Datei im Storage
     * @return void
     */
    public function handleUploadedFile(string $filePath): void
    {
        Log::info('CSV-Import gestartet', [
            'importer' => self::class,
            'file' => $filePath,
            'model' => Product::class,
        ]);

        $handle = fopen(storage_path('app/' . $filePath), 'r');
        $header = null;
        $rowCount = 0;

        while (($row = fgetcsv($handle, 1000, ';')) !== false) {
            if (!$header) {
                $header = $row;
                continue;
            }

            $row = array_combine($header, $row);
            $mappedData = $this->mapCsvRow($row);
            $rowCount++;

            try {
                $product = Product::create($mappedData);
                Log::debug('Importiert (CSV)', $product->toArray());
            } catch (\Throwable $e) {
                Log::error("Fehler beim Import in Zeile {$rowCount}", [
                    'exception' => $e->getMessage(),
                    'row' => $row
                ]);
            }
        }

        fclose($handle);

        Log::info('CSV-Import abgeschlossen', ['importierte_zeilen' => $rowCount]);
    }

    /**
     * Verarbeitet eine hochgeladene XML-Datei und speichert Produkte.
     *
     * @param string $filePath Pfad zur hochgeladenen XML-Datei im Storage
     * @return void
     */
    public function handleUploadedXmlFile(string $filePath): void
    {
        Log::info('XML-Import gestartet', [
            'importer' => self::class,
            'file' => $filePath,
            'model' => Product::class,
        ]);

        $xml = simplexml_load_file(storage_path('app/' . $filePath));

        if (!$xml || !isset($xml->PRODUCTS->PRODUCTITEM)) {
            Log::error('Fehler beim Einlesen der XML-Datei oder keine Produkte gefunden', ['file' => $filePath]);
            return;
        }

        $rowCount = 0;

        foreach ($xml->PRODUCTS->PRODUCTITEM as $productNode) {
            $row = json_decode(json_encode($productNode), true);
            $mappedData = $this->mapXmlRow($row);
            $rowCount++;

            try {
                $product = Product::create($mappedData);
                Log::debug('Importiert (XML)', $product->toArray());
            } catch (\Throwable $e) {
                Log::error("Fehler beim Import in Datensatz {$rowCount}", [
                    'exception' => $e->getMessage(),
                    'row' => $row
                ]);
            }
        }

        Log::info('XML-Import abgeschlossen', ['importierte_zeilen' => $rowCount]);
    }

    /**
     * Verarbeitet Produktdaten direkt von einer API-URL (XML).
     *
     * @param string $url API-Endpunkt für den Singing Rock Feed
     * @return void
     */
    public function handleFromUrl(string $url): void
    {
        Log::info('API-Import gestartet', [
            'importer' => self::class,
            'url' => $url,
            'model' => Product::class,
        ]);

        try {
            $response = Http::timeout(30)->get($url);

            if ($response->failed()) {
                Log::error('Fehler beim Abrufen der API-Daten', ['status' => $response->status()]);
                return;
            }

            $xmlContent = $response->body();
            $xml = simplexml_load_string($xmlContent);

            if (!$xml || !isset($xml->PRODUCTS->PRODUCTITEM)) {
                Log::error('Fehler beim Einlesen der API-XML-Daten oder keine Produkte gefunden', ['url' => $url]);
                return;
            }

            $rowCount = 0;

            foreach ($xml->PRODUCTS->PRODUCTITEM as $productNode) {
                $row = json_decode(json_encode($productNode), true);
                $mappedData = $this->mapXmlRow($row);
                $rowCount++;

                try {
                    $product = Product::create($mappedData);
                    Log::debug('Importiert (API)', $product->toArray());
                } catch (\Throwable $e) {
                    Log::error("Fehler beim API-Import in Datensatz {$rowCount}", [
                        'exception' => $e->getMessage(),
                        'row' => $row
                    ]);
                }
            }

            Log::info('API-Import abgeschlossen', ['importierte_zeilen' => $rowCount]);
        } catch (\Throwable $e) {
            Log::error('Allgemeiner Fehler beim API-Import', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Mappt eine CSV-Zeile auf die Felder der Products-Tabelle.
     *
     * @param array $row Array der CSV-Zeile (Spaltenname => Wert)
     * @return array Gemappte Produktdaten
     */
    private function mapCsvRow(array $row): array
    {
        return [
            'product_name' => $row['description'] ?? 'Unbenanntes Produkt',
            'product_number' => $row['product_code'] ?? null,
            'description' => $row['description'] ?? null,
            'ean' => $row['ean'] ?? null,
            'width' => $row['width'] ?? null,
            'length' => $row['length'] ?? null,
            'height' => $row['height'] ?? null,
            'pcs_per_box' => $row['pcs_per_box'] ?? null,
            'box_width' => $row['box_width'] ?? null,
            'box_length' => $row['box_length'] ?? null,
            'box_height' => $row['box_height'] ?? null,
            'weight' => $row['weight'] ?? null,
            'manufacturer_id' => 3,
            'slug' => $row['description'] ?? uniqid('produkt-'),
            'status' => 'draft',
        ];
    }

    /**
     * Mappt einen XML-Knoten auf die Felder der Products-Tabelle.
     * Beschreibung & Kurzbeschreibung bleiben im Original-HTML.
     *
     * @param array $row Array mit den XML-Produktdaten
     * @return array Gemappte Produktdaten
     */
    private function mapXmlRow(array $row): array
    {
        return [
            'product_name' => $this->normalizeValue($row['ARTICLE_NAME'] ?? 'Unbenanntes Produkt'),
            'product_number' => $this->normalizeValue($row['ARTICLE'] ?? null),
            'description' => $this->normalizeValue($row['ARTICLE_NAME'] ?? null),
            'eancode' => $this->normalizeValue($row['EAN'] ?? null),
            'description' => $this->normalizeValue($row['DESCRIPTION'] ?? null, true), // HTML behalten
            'short_description' => $this->normalizeValue($row['SHORT_DESCRIPTION'] ?? null, true), // HTML behalten
            'unit' => $this->normalizeValue($row['UNIT'] ?? null),
            'weight' => $this->normalizeValue($row['WEIGHT'] ?? null),
            'manufacturer_id' => 3,
            'slug' => $this->normalizeValue($row['ARTICLE'] ?? uniqid('produkt-')),
            'status' => 'draft',
        ];
    }

    /**
     * Normalisiert Werte aus XML.
     * - Lässt HTML unverändert, wenn $allowHtml = true
     * - Macht aus Arrays einfache Strings
     *
     * @param mixed $value
     * @param bool $allowHtml Ob HTML beibehalten werden soll
     * @return string|null
     */
    private function normalizeValue($value, bool $allowHtml = false): ?string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map(fn($v) => is_string($v) ? $v : '', $value)));
        }
        return $value ? ($allowHtml ? (string)$value : trim(strip_tags((string)$value))) : null;
    }
}
