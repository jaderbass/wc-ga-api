<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use App\Models\Manufacturer;
use App\Support\Import\SingingRock\Map;
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
    public function __construct(
        private ?Manufacturer $manufacturer = null,
        private ?int $manufacturerId = null,
    ) {
        if (!$this->manufacturer && $this->manufacturerId) {
            $this->manufacturer = Manufacturer::find($this->manufacturerId);
        } elseif ($this->manufacturer && !$this->manufacturerId) {
            $this->manufacturerId = $this->manufacturer->id;
        }
    }

    private function getManufacturerId(): int
    {
        // Fallback 3 nur als letzte Rettung – idealerweise brauchst du den nie mehr
        return $this->manufacturerId
            ?? $this->manufacturer?->id
            ?? 3;
    }

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
            'manufacturer_id' => $this->manufacturer?->id,
        ]);

        try {
            $client = Http::timeout(30);

            if ($this->manufacturer) {
                // Variante 1: Basic Auth (user/pass)
                if ($this->manufacturer->api_user && $this->manufacturer->api_password) {
                    $client = $client->withBasicAuth(
                        $this->manufacturer->api_user,
                        $this->manufacturer->api_password
                    );
                }

                // Variante 2: Bearer-Token
                if ($this->manufacturer->api_token && !$this->manufacturer->api_user && !$this->manufacturer->api_password) {
                    $client = $client->withToken($this->manufacturer->api_token);
                }
            }

            $response = $client->get($url);

            if ($response->failed()) {
                Log::error('Fehler beim Abrufen der API-Daten', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
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
            Log::error('Allgemeiner Fehler beim API-Import', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
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
            'manufacturer_id' => $this->getManufacturerId(),
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

        $description = $this->normalizeValue($row['DESCRIPTION'] ?? null, true);

        // Harness-Size-Tabelle (HTML) erzeugen
        $harnessTableHtml = Map::harnessSizeTableHtml($row);

        // Tabelle an Description anhängen (nur wenn vorhanden)
        $description = Map::appendHtmlBlock(
            $description,
            $harnessTableHtml,
            'Size table'
        );

        $norms = Map::normsDisplay($row);

        $materials = Map::materialsDisplay($row['MATERIAL_COMPOSITION'] ?? null);

        return [
            'product_name'          => $this->normalizeValue($row['ARTICLE_NAME'] ?? 'Unbenanntes Produkt'),
            'product_number'        => $this->normalizeValue($row['ARTICLE'] ?? null),
            'description'           => $description,
            'eancode'               => $this->normalizeValue($row['EAN'] ?? null),
            'short_description'     => $this->normalizeValue($row['SHORT_DESCRIPTION'] ?? null, true),
            'unit'                  => $this->normalizeValue($row['UNIT'] ?? null),
            'weight'                => $this->normalizeValue($row['WEIGHT'] ?? null),

            'norms'                 => $norms !== '' ? $norms : null,

            'manufacturer_id'       => $this->getManufacturerId(),
            'slug'                  => $this->normalizeValue($row['ARTICLE'] ?? uniqid('produkt-')),
            'status'                => 'draft',
            'materials'             => $materials !== '' ? $materials : null,
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
    private function normalizeValue(mixed $value, bool $allowHtml = false): ?string
    {
        if ($value === null) {
            return null;
        }

        // Singing Rock XML can return arrays (e.g. CATEGORIES/CATEGORY)
        if (is_array($value)) {
            // Wenn es ein Array aus Scalars ist: zu String zusammenziehen
            $flat = [];

            array_walk_recursive($value, static function ($v) use (&$flat): void {
                if ($v === null) {
                    return;
                }

                $v = trim((string) $v);

                if ($v !== '') {
                    $flat[] = $v;
                }
            });

            if ($flat === []) {
                return null;
            }

            // Dedupe + join (wahlweise anderes Trennzeichen)
            $flat = array_values(array_unique($flat));

            return implode(' | ', $flat);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // deine bestehende Logik (HTML erlauben etc.)
        return $value;
    }
}
