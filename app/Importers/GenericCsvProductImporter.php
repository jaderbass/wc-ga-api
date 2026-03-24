<?php

namespace App\Importers;

use App\Importers\Contracts\CsvImporterContract;
use App\Support\ImportLog;
use App\Services\Categories\ProductCategorySyncService;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\ProductMeta;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class GenericCsvProductImporter
 *
 * Importiert Produktdaten und Produktvarianten aus einer CSV-Datei anhand eines konfigurierbaren Mappings.
 * Unterstützt Gruppierung nach Hauptprodukt und automatische Zuordnung von Farb- und Größenvarianten.
 *
 * Erwartet eine Mapping-Datei unter config/import_mappings/<mappingName>.php.
 */
class GenericCsvProductImporter implements CsvImporterContract
{
    protected array $mapping;

    /**
     * Initialisiert den Importer mit dem spezifischen Mapping und der Hersteller-ID.
     *
     * @param string $mappingFile Der Name der Mapping-Datei (ohne .php), die unter `config/import_mappings/` liegt.
     * @param int|null $manufacturerId Die ID des Herstellers, dem die importierten Produkte zugeordnet werden.
     */
    public function __construct(
        protected ?string $mappingFile = null,
        protected ?int $manufacturerId = null
    ) {
        // ❌ temporäre Edelrid-Sperre entfernen
        // ✅ Mapping robust laden (aus config() ODER Datei)
        if ($this->mappingFile === null) {
            throw new \RuntimeException('GenericCsvProductImporter benötigt $mappingFile (z. B. "petzl", "edelrid").');
        }

        $this->mapping = $this->loadMapping($this->mappingFile);
    }

    /**
     * Ermittelt die Author-ID für diesen Importlauf.
     *
     * Aktuell:
     * - verwendet den eingeloggten Benutzer (auth()->id()).
     * Später könnte hier noch eine explizite Zuweisung ergänzt werden.
     */
    protected function resolveAuthorId(): ?int
    {
        $user = Auth::user();

        return $user?->id;
    }

    /**
     * Liest ein optionales Steuer-Flag aus dem Import-Mapping.
     *
     * Wird genutzt, um herstellerspezifisches Verhalten
     * (z. B. Aliens: SKU-Prefixe, Auto-Features, Lookup-Strategie)
     * ohne harte If-Abfragen im Code zu steuern.
     *
     * Beispiel:
     *   $this->flag('auto_features', false)
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function flag(string $key, mixed $default = null): mixed
    {
        $flags = $this->mapping['flags'] ?? [];
        return is_array($flags) && array_key_exists($key, $flags) ? $flags[$key] : $default;
    }

    /**
     * Importiert eine CSV-Datei, erkennt das Hersteller-Mapping automatisch an den Headern
     * und gruppiert die Zeilen robust nach dem Mapping.
     *
     * Auto-Mapping:
     * - Erkennt Edelrid an „Artikelbezeichnung“/„Artikelnummer“ (deutsche Header)
     * - Erkennt Petzl an „Product name“/„Reference“ (englische Header)
     * - Überschreibt nur dann das Mapping, wenn es noch nicht gesetzt ist
     *
     * @param string $filePath
     * @return void
     */
    public function import(string $filePath): void
    {
        $csv = \League\Csv\Reader::createFromPath($filePath, 'r');
        $csv->setDelimiter(';');
        $csv->skipEmptyRecords();

        $rows = iterator_to_array($csv->getRecords());
        $rows = array_values($rows);

        $headerRowIndex = $this->detectHeaderRowIndex($rows);

        $rawHeaders = $rows[$headerRowIndex] ?? [];
        $rawHeaders = array_values($rawHeaders);
        $rawHeaders = array_map(function ($header) {
            $header = is_string($header) ? $header : (string) $header;

            $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            $header = trim($header);

            return $header;
        }, $rawHeaders);

        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $mappingType = $this->detectMappingTypeFromHeaders($rawHeaders);

        $headers = $this->buildNormalizedHeaders($rawHeaders, $mappingType);

        $records = [];
        foreach ($dataRows as $row) {
            $row = array_values($row);
            $row = array_pad($row, count($headers), null);
            $row = array_slice($row, 0, count($headers));

            $records[] = array_combine($headers, $row);
        }

        if ($mappingType === 'petzl') {
            $records = $this->fillForwardPetzlMergedColumns($records);

            $firstRow = $records[0] ?? [];
        }

        if ((empty($this->mapping) || !is_array($this->mapping)) && $mappingType !== null) {
            $this->mapping = config('import_mappings.' . $mappingType);
            ImportLog::debug('Auto-selected mapping', ['mapping' => $mappingType]);
        }

        ImportLog::debug('Active mapping snapshot', [
            'product_keys'   => array_keys($this->mapping['product'] ?? []),
            'variation_keys' => array_keys($this->mapping['variation'] ?? []),
            'vf_keys'        => array_keys($this->mapping['variation_fields'] ?? []),
        ]);

        $normalized = collect($records)->map(function (array $row) {
            foreach ($row as $k => $v) {
                $row[$k] = $this->normalizeCellValue($v);
            }

            return $row;
        });

        ImportLog::debug('Normalized record sample', [
            'sample' => array_slice($normalized->values()->toArray(), 0, 3),
        ]);

        $groupBy = $this->mapping['group_by'] ?? null;
        $groupByCols = is_array($groupBy)
            ? array_values($groupBy)
            : ((is_string($groupBy) && $groupBy !== '') ? [$groupBy] : []);

        $fallbackCols = ['Artikelbezeichnung', 'Artikelnummer'];

        $clean = function (?string $value): string {
            if ($value === null) {
                return '';
            }

            $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $value) ?? $value;
            $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

            return trim($s);
        };

        $firstNonEmpty = function (array $row, array $candidates) use ($clean): string {
            if (empty($candidates)) {
                return '';
            }

            $map = [];
            foreach (array_keys($row) as $key) {
                $norm = mb_strtolower($clean((string) $key));
                $map[$norm] = $key;
            }

            foreach ($candidates as $cand) {
                $normCand = mb_strtolower($clean($cand));

                if (isset($map[$normCand])) {
                    $val = $clean((string) ($row[$map[$normCand]] ?? ''));
                    if ($val !== '') {
                        return $val;
                    }
                }

                if (array_key_exists($cand, $row)) {
                    $val = $clean((string) ($row[$cand] ?? ''));
                    if ($val !== '') {
                        return $val;
                    }
                }
            }

            return '';
        };

        $indexed = $normalized->values()->map(function (array $row, int $i) {
            $row['__row_index'] = $i;

            return $row;
        });

        $grouped = $indexed->groupBy(function (array $row) use ($groupByCols, $fallbackCols, $firstNonEmpty, $clean): string {
            if (!empty($groupByCols)) {
                $val = $firstNonEmpty($row, $groupByCols);
                if ($val !== '') {
                    return $clean($val);
                }
            }

            $val = $firstNonEmpty($row, $fallbackCols);
            if ($val !== '') {
                return $clean($val);
            }

            $i = $row['__row_index'] ?? 'x';

            return '__ROW__:' . $i;
        });

        ImportLog::debug('CSV group keys (normalized)', [
            'count_groups' => $grouped->count(),
        ]);

        foreach ($grouped as $groupKey => $rows) {
            ImportLog::debug('Import group', [
                'groupKey' => $groupKey,
                'rows'     => $rows->count(),
            ]);

            try {
                DB::transaction(function () use ($groupKey, $rows) {
                    $this->importProductGroup($groupKey, $rows);
                });
            } catch (\Throwable $e) {
                Log::error('Import group failed', [
                    'groupKey'  => $groupKey,
                    'rows'      => $rows->count(),
                    'exception' => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * Füllt bei Petzl CSV-Zeilen leere Werte in zusammengeführten Excel-Spalten
     * aus der vorherigen Zeile auf.
     *
     * Hintergrund:
     * In der Excel-Datei sind u. a. "Product name" und "Reference" teils über zwei
     * Zeilen zusammengeführt. Beim CSV-Export steht der Wert dann nur in der ersten
     * Zeile, die Folgezeile bleibt leer.
     *
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function fillForwardPetzlMergedColumns(array $records): array
    {
        $carryColumns = [
            'Status',
            'Date status',
            'Product name',
            'Reference',
        ];

        $lastSeen = [];

        foreach ($records as $index => $row) {
            foreach ($carryColumns as $column) {
                $value = $row[$column] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                if ($value !== null && $value !== '') {
                    $lastSeen[$column] = $value;
                    continue;
                }

                if (array_key_exists($column, $lastSeen)) {
                    $records[$index][$column] = $lastSeen[$column];
                }
            }
        }

        return $records;
    }

    /**
     * Sucht in den CSV-Zeilen die wahrscheinlich echte Header-Zeile.
     *
     * @param array<int, array<int, mixed>> $rows
     * @return int
     */
    private function detectHeaderRowIndex(array $rows): int
    {
        foreach ($rows as $index => $row) {
            $cells = array_map(
                fn($value) => is_string($value) ? trim($value) : (string) $value,
                array_values($row)
            );

            $normalized = array_map(function (string $value): string {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value; // UTF-8 BOM am Anfang
                $value = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $value) ?? $value;
                $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

                return mb_strtolower(trim($value));
            }, $cells);

            $hasPetzl = in_array('product name', $normalized, true)
                || in_array('reference', $normalized, true);

            $hasEdelrid = in_array('artikelbezeichnung', $normalized, true)
                || in_array('artikelnummer', $normalized, true);

            if ($hasPetzl || $hasEdelrid) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Erkennt anhand der CSV-Header grob den Hersteller bzw. das Mapping.
     *
     * @param array<int, string> $headers
     * @return string|null
     */
    private function detectMappingTypeFromHeaders(array $headers): ?string
    {
        $normalizeHeader = function (string $header): string {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header; // 1. BOM weg
            $value = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $value) ?? $value; // 2. Steuerzeichen
            $value = str_replace(["\r", "\n"], ' ', $value); // 3. Zeilenumbrüche normalisieren
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value; // 4. Spaces normalisieren

            return mb_strtolower(trim($value));
        };

        $normalizedHeaders = array_map($normalizeHeader, $headers);

        $isEdelrid = in_array('artikelbezeichnung', $normalizedHeaders, true)
            || in_array('artikelnummer', $normalizedHeaders, true);

        $isPetzl = in_array('product name', $normalizedHeaders, true)
            || in_array('reference', $normalizedHeaders, true);

        if ($isEdelrid) {
            return 'edelrid';
        }

        if ($isPetzl) {
            return 'petzl';
        }

        return null;
    }

    /**
     * Baut aus Roh-Headern eindeutige und importierbare Header.
     *
     * Für Petzl werden wiederholte "Unit"-Spalten kontextbezogen der jeweils
     * vorherigen Wertespalte zugeordnet, z. B.:
     * Volume + Unit => Volume_Unit
     *
     * Für andere Hersteller werden nur Duplikate mit Suffixen (_2, _3, ...)
     * eindeutig gemacht.
     *
     * @param array<int, string> $headers
     * @param string|null $mappingType
     * @return array<int, string>
     */
    private function buildNormalizedHeaders(array $headers, ?string $mappingType): array
    {
        if ($mappingType === 'petzl') {
            return $this->makePetzlHeadersContextAware($headers);
        }

        return $this->makeHeadersUnique($headers);
    }

    /**
     * Macht doppelte Header eindeutig, z. B. "Unit", "Unit" => "Unit", "Unit_2".
     *
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    private function makeHeadersUnique(array $headers): array
    {
        $seen = [];
        $out = [];

        foreach ($headers as $header) {
            $base = $this->normalizeHeader(
                is_string($header) ? $header : (string) $header,
                false
            );

            if ($base === '') {
                $base = 'column';
            }

            if (!isset($seen[$base])) {
                $seen[$base] = 1;
                $out[] = $base;
                continue;
            }

            $seen[$base]++;
            $out[] = $base . '_' . $seen[$base];
        }

        return $out;
    }

    /**
     * Macht Petzl-Header eindeutig und ordnet:
     * - Unit-Spalten der vorherigen Spalte zu
     * - leere Header (aus Excel-Merge) der vorherigen Spalte zu (z. B. Specifications_2)
     *
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    private function makePetzlHeadersContextAware(array $headers): array
    {
        $seen = [];
        $out = [];
        $lastValueHeader = null;

        foreach ($headers as $header) {
            $header = $this->normalizeHeader(is_string($header) ? $header : (string) $header, false);

            if ($header === '') {
                // 👉 WICHTIG: leere Header vom Excel-Merge
                if ($lastValueHeader !== null) {
                    $base = $lastValueHeader . '_2';
                } else {
                    $base = 'column';
                }
            } elseif ($this->isUnitHeader($header)) {
                $base = $lastValueHeader !== null
                    ? $lastValueHeader . '_Unit'
                    : 'Unit';
            } else {
                $base = $header;
                $lastValueHeader = $header;
            }

            if (!isset($seen[$base])) {
                $seen[$base] = 1;
                $out[] = $base;
                continue;
            }

            $seen[$base]++;
            $out[] = $base . '_' . $seen[$base];
        }

        return $out;
    }

    /**
     * Prüft, ob ein Header eine generische Unit-Spalte ist.
     *
     * @param string $header
     * @return bool
     */
    private function isUnitHeader(string $header): bool
    {
        return mb_strtolower(trim($header)) === 'unit';
    }

    /**
     * Liefert den ersten nicht-leeren Zellwert aus $row für eine Spalten-Spezifikation.
     * $spec kann 'Spaltenname' oder ['Alt1','Alt2',...] sein.
     */
    /**
     * Liefert den ersten nicht-leeren Zellwert aus $row für eine Spalten-Spezifikation.
     * $spec kann 'Spaltenname' oder ['Alt1','Alt2', …] sein.
     *
     * @param  array<string,mixed>      $row
     * @param  string|array<int,string> $spec
     * @return string|null
     */
    private function cell(array $row, string|array $spec): ?string
    {
        if (is_array($spec)) {
            foreach ($spec as $col) {
                if (array_key_exists($col, $row) && trim((string) $row[$col]) !== '') {
                    return trim((string) $row[$col]);
                }
            }
            return null;
        }

        return array_key_exists($spec, $row) && trim((string) $row[$spec]) !== ''
            ? trim((string) $row[$spec])
            : null;
    }

    /**
     * Importiert eine Produkt-Gruppe (Hauptprodukt + Varianten).
     *
     * - Spaltenzugriffe robust via firstNonEmptyFromRow() (Umlaute/NBSP tolerant)
     * - Short-Description-Fallback aus Description
     * - Upsert schema-robust (nur existierende Spalten) + forceFill()
     * - Klare Diagnose-Logs: welche Felder werden geschrieben / gefiltert / Variantenanzahl
     *
     * @param string                                $groupKey
     * @param \Illuminate\Support\Collection<int,array<string,mixed>> $rows
     * @return void
     */
    protected function importProductGroup(string $groupKey, \Illuminate\Support\Collection $rows): void
    {
        $payload = null;
        $writablePayload = null;


        // reference kann String oder Array sein
        $referenceKey  = $this->mapping['reference'] ?? null;
        $referenceCols = is_array($referenceKey)
            ? $referenceKey
            : ((is_string($referenceKey) && $referenceKey !== '') ? [$referenceKey] : []);

        // Fallbacks: Edelrid 'Artikelnummer', generisch 'Reference'
        if (empty($referenceCols) || $referenceCols === ['Reference']) {
            $referenceCols = ['Artikelnummer', 'Reference'];
        }

        /**
         * Erzeuge die Produkt-Payload aus dem Mapping.
         * Nutzt primär das 'fields'-Mapping, erweitert um 'product' als Override.
         * 'product' überschreibt ggf. Einträge aus 'fields'.
         */
        $baseProductMapping = $this->mapping['product'] ?? [];
        $fieldMapping       = $this->mapping['fields'] ?? [];

        // Effektives Produkt-Mapping: zuerst alle Felder, dann explizite Produkt-Felder
        $productMapping = array_merge($fieldMapping, $baseProductMapping);
        $productPayload = [];

        ImportLog::debug('Edelrid product mapping keys', [
            'keys' => array_keys($productMapping),
        ]);

        foreach ($productMapping as $dbField => $csvColumn) {
            $candidates = is_array($csvColumn) ? $csvColumn : [$csvColumn];

            // erste Zeile in der Gruppe mit nicht-leerem Wert (robust) finden
            $sourceRow = $rows->first(function (array $row) use ($candidates) {
                $v = $this->firstNonEmptyFromRow($row, $candidates);
                return $v !== null && trim((string)$v) !== '';
            });

            $resolved = null;
            if ($sourceRow) {
                $val = $this->firstNonEmptyFromRow($sourceRow, $candidates);
                if ($val !== null && trim((string)$val) !== '') {
                    $resolved = trim((string)$val);
                }
            }

            // Ausgangswert für das Feld
            $finalForField = $resolved;

            // Optional: Transform für dieses Feld anwenden
            if (!empty($this->mapping['transforms'][$dbField])) {
                $transform = $this->mapping['transforms'][$dbField];

                // Variante: [ClassName::class, 'method']
                if (is_array($transform) && count($transform) === 2) {
                    [$class, $method] = $transform;
                    if (class_exists($class) && method_exists($class, $method)) {
                        $finalForField = $class::$method($resolved, $sourceRow ?? []);
                    }
                }

                // Variante: Closure
                if ($transform instanceof \Closure) {
                    $finalForField = $transform($resolved, $sourceRow ?? []);
                }
            }

            // Transform-Ergebnis in Payload schreiben
            if (is_array($finalForField)) {
                // Unterscheide numerische Arrays (z. B. Liste von Bild-URLs)
                // von assoziativen Arrays (z. B. dimensions_raw + *_mm)
                $keys        = array_keys($finalForField);
                $isSequential = $keys === range(0, count($finalForField) - 1);

                if ($isSequential) {
                    // einfache Liste → bleibt auf dem ursprünglichen Feld
                    $productPayload[$dbField] = $finalForField;
                } else {
                    // assoziatives Array → mehrere Felder in Payload schreiben
                    foreach ($finalForField as $key => $value) {
                        $productPayload[$key] = $value;
                    }
                }
            } elseif ($finalForField !== null && trim((string) $finalForField) !== '') {
                $productPayload[$dbField] = $finalForField;
            }

            // Diagnose-Log: zeigt pro Feld, welche Kandidaten probiert wurden und was rauskam
            ImportLog::debug('Mapping check', [
                'group'      => $groupKey,
                'field'      => $dbField,
                'candidates' => $candidates,
                'resolved'   => $resolved, // Originalwert vor Transform
            ]);
        }

        // Fallback: Shortdescription aus Description (max 255, HTML raus)
        if (
            (!array_key_exists('short_description', $productPayload) ||
                trim((string)($productPayload['short_description'] ?? '')) === '')
            && !empty($productPayload['description'])
        ) {
            $productPayload['short_description'] = \Illuminate\Support\Str::limit(
                strip_tags((string) $productPayload['description']),
                255
            );
        }

        // original_product_name muss gesetzt sein, sonst wird später der berechnete Name erneut als Designation verwendet.
        if (
            (!array_key_exists('original_product_name', $productPayload) || trim((string) ($productPayload['original_product_name'] ?? '')) === '')
            && \Illuminate\Support\Facades\Schema::hasColumn('products', 'original_product_name')
        ) {
            $productPayload['original_product_name'] = trim((string) $groupKey);
        }

        // Name/Slug/Feste Werte
        $name = $productPayload['product_name'] ?? trim($groupKey) ?: 'Unnamed Product';
        $slug = \Illuminate\Support\Str::slug($name) ?: \Illuminate\Support\Str::slug('product-' . uniqid());

        $finalProductPayload = array_merge($productPayload, [
            'product_name'    => $name,
            // Achtung: diese Keys schreiben wir nur, wenn Spalten existieren (siehe unten)
            'product_type'    => 'variable',
            'manufacturer_id' => $this->manufacturerId,
            'status'          => 'draft',
        ]);

        $authorId = $this->resolveAuthorId();

        // --- Upsert schema-robust + Diagnose ---
        /** @var \App\Models\Product $tmpModel */
        $tmpModel   = app(\App\Models\Product::class);
        $tableName  = $tmpModel->getTable();
        $columns    = \Illuminate\Support\Facades\Schema::getColumnListing($tableName);
        $columnSet  = array_flip($columns);

        // Welche Felder KÖNNEN wir wirklich schreiben?
        $writablePayload = array_intersect_key($finalProductPayload, $columnSet);
        $droppedKeys     = array_diff(array_keys($finalProductPayload), array_keys($writablePayload));

        ImportLog::debug('Product payload before upsert', [
            'group'           => $groupKey,
            'slug'            => $slug,
            'final_payload'   => $finalProductPayload,
            'writable_payload' => $writablePayload,
            'dropped_keys'    => $droppedKeys,
        ]);

        if (($finalProductPayload['product_number'] ?? null) === '717620003600') {
            ImportLog::debug('DEBUG Bud payload', [
                'group'         => $groupKey,
                'final_payload' => $finalProductPayload,
            ]);
        }

        // Basisdaten für Create (nur vorhandene Spalten)
        $baseCreate = [];
        foreach (
            [
                'slug'            => $slug,
                'manufacturer_id' => $this->manufacturerId,
                'product_name'    => $name,
                'product_type'    => $finalProductPayload['product_type'] ?? null,
                'status'          => $finalProductPayload['status'] ?? null,
                'author_id'       => $authorId,
            ] as $col => $val
        ) {
            if (isset($columnSet[$col]) && $val !== null) {
                $baseCreate[$col] = $val;
            }
        }

        // Produkt holen/erstellen
        $productLookupBy = (string) ($this->flag('product_lookup_by', 'slug') ?? 'slug');

        $query = \App\Models\Product::query();

        if ($productLookupBy === 'sku') {
            // Für Aliens: Parent-SKU = Prefix + Produkt-ID
            $productIdSpec = $this->mapping['group_by'] ?? null;
            $productIdCol  = is_array($productIdSpec) ? ($productIdSpec[0] ?? null) : $productIdSpec;
            $aliensProductId = $productIdCol ? $this->firstNonEmptyFromRow($rows->first() ?? [], [$productIdCol]) : null;
            $aliensProductId = $aliensProductId !== null ? trim((string) $aliensProductId) : null;

            $productSkuPrefix = (string) ($this->flag('product_sku_prefix', '') ?? '');
            $prefixedSku = ($productSkuPrefix !== '' && $aliensProductId) ? $productSkuPrefix . $aliensProductId : null;

            if ($prefixedSku) {
                $query->where('sku', $prefixedSku);
                // stellen wir sicher, dass SKU im Payload gesetzt ist
                $writablePayload['sku'] = $prefixedSku;
            } else {
                // Fallback auf slug
                $query->where('slug', $slug);
            }
        } else {
            $query->where('slug', $slug);
        }

        $product = $query->first();

        // Zusatzsicherung: berechneten Namen niemals aus Importdaten überschreiben
        if (array_key_exists('product_name', $writablePayload)) {
            // unset($payload['product_name'], $writablePayload['product_name']);
            if (is_array($payload)) {
                unset($payload['product_name']);
            }

            if (isset($writablePayload) && is_array($writablePayload)) {
                unset($writablePayload['product_name']);
            }
        }

        if ($product) {
            $product->forceFill($writablePayload)->save();
        } else {
            $product = \App\Models\Product::create($baseCreate);
            if (!empty($writablePayload)) {
                $product->forceFill($writablePayload)->save();
            }
        }

        app(ProductCategorySyncService::class)->sync($product);

        Log::info('Product upserted', [
            'id'              => $product->id,
            'name'            => $product->product_name,
            'product_number'  => $product->product_number,
            'ean'             => $product->external_url,
        ]);

        /**
         * Speichert Aliens-Feature-Spalten automatisch als product_meta.
         *
         * Unterstützt:
         * - Alle Spalten mit Prefix "Feature:"
         * - Zusätzlich strukturierte Triples:
         *   - Feature Name
         *   - Feature Value
         *   - Feature Position
         *
         * Die Daten werden bewusst nicht normalisiert,
         * sondern 1:1 aus der CSV übernommen, um maximale
         * Nachverfolgbarkeit zum Lieferantenfeed zu behalten.
         */
        if ($this->flag('auto_features', false) === true) {
            Log::info('Aliens auto_features: enabled', [
                'mapping_flags' => $this->mapping['flags'] ?? null,
                'rows_count' => is_countable($rows) ? count($rows) : null,
            ]);
            foreach ($rows as $row) {
                if (($this->flag('debug_features', false) === true)) {
                    $keys = array_keys($row);
                    $sample = array_slice($keys, 0, 80);

                    Log::info('Aliens auto_features: row keys sample', [
                        'keys_count' => count($keys),
                        'sample' => $sample,
                    ]);
                }
                foreach ($row as $colName => $raw) {
                    if (!is_string($colName)) continue;

                    $col = is_string($colName) ? $colName : '';
                    $col = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $col) ?? $col;
                    $col = trim($col);

                    // toleranter: "Feature:" oder "Feature :"
                    $colNorm = preg_replace('/\s+/u', ' ', $col) ?? $col;
                    $colNorm = str_replace('Feature :', 'Feature:', $colNorm);

                    if (!str_starts_with($colNorm, 'Feature:')) {
                        continue;
                    }

                    $val = is_string($raw) ? trim($raw) : (string) $raw;
                    $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
                    $val = trim($val);

                    if ($val === '') continue;

                    $featureName = trim(substr($colNorm, strlen('Feature:')));
                    $featureName = preg_replace('/\s+/u', ' ', $featureName) ?? $featureName;
                    $featureName = trim($featureName);

                    if ($featureName === '') continue;

                    if (($this->flag('debug_features', false) === true)) {
                        Log::info('Aliens auto_features: writing meta', [
                            'product_id' => $product->id,
                            'col' => $colNorm ?? $col,
                            'featureName' => $featureName,
                            'meta_key' => 'feature.' . Str::slug($featureName, '_'),
                            'value_preview' => mb_substr($val, 0, 120),
                        ]);
                    }


                    ProductMeta::updateOrCreate(
                        [
                            'product_id'   => $product->id,
                            'variation_id' => null,
                            'scope'        => 'product',
                            'key'          => 'feature.' . Str::slug($featureName, '_'),
                        ],
                        ['value' => $val]
                    );
                }

                $payload = null;

                // Feature Name/Value/Position als JSON-Liste (sofern befüllt)
                $fn = $row['Feature Name'] ?? null;
                $fv = $row['Feature Value'] ?? null;
                $fp = $row['Feature Position'] ?? null;

                $fn = is_string($fn) ? trim($fn) : null;
                $fv = is_string($fv) ? trim($fv) : null;
                $fp = is_string($fp) ? trim($fp) : null;

                if (($fn ?? '') !== '' || ($fv ?? '') !== '' || ($fp ?? '') !== '') {
                    $featuresListPayload  = [
                        [
                            'name' => $fn,
                            'value' => $fv,
                            'position' => $fp !== null && $fp !== '' ? (int) $fp : null,
                        ],
                    ];

                    ProductMeta::updateOrCreate(
                        [
                            'product_id'   => $product->id,
                            'variation_id' => null,
                            'scope'        => 'product',
                            'key'          => 'features.list_json',
                        ],
                        ['value' => json_encode($featuresListPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
                    );
                }
            }
        }

        // --- Varianten importieren ---
        $skipped = 0;
        $imported = 0;
        $loggedRefDiag = false;

        foreach ($rows as $row) {
            $ref = $this->firstNonEmptyFromRow($row, $referenceCols);
            $ref = $ref !== null ? trim((string) $ref) : '';

            if (!$loggedRefDiag) {
                /**
                 * ! Mit Flag !!!
                 */
                ImportLog::debug('Reference detection (group)', [
                    'group'          => $groupKey,
                    'reference_cols' => $referenceCols,
                    'sample_ref'     => $ref,
                ]);
                $loggedRefDiag = true;
            }

            if ($ref === '') {
                $skipped++;
                continue;
            }

            // Variante anlegen/aktualisieren (bestehende Logik)
            $this->importVariation($product, $row);
            $imported++;
        }

        // Nachzählung (falls Relation vorhanden)
        try {
            $relCount = method_exists($product, 'variations') ? $product->variations()->count() : null;
        } catch (\Throwable $e) {
            $relCount = null;
        }

        if ($skipped > 0 || $imported > 0) {
            Log::info('Variation summary', [
                'group'          => $groupKey,
                'imported'       => $imported,
                'skipped'        => $skipped,
                'relation_count' => $relCount,
            ]);
        }
    }

    /**
     * Importiert oder aktualisiert eine einzelne Produktvariante.
     *
     * Nutzt die SKU (Referenz) aus der CSV-Zeile, um eine Variante zu identifizieren.
     * Führt ein `updateOrCreate` für die Variante durch und stößt die Zuweisung
     * der Attribute (z.B. Farbe, Größe) an.
     *
     * @param Product $product Das übergeordnete Hauptprodukt.
     * @param array   $row     Die CSV-Zeile, die die Daten der Variante enthält.
     * @return void
     */
    protected function importVariation(Product $product, array $row)
    {
        // reference kann String ODER Array sein -> robust per cell()
        $referenceSpec = $this->mapping['reference'] ?? 'Reference';
        $ref = $this->cell($row, is_array($referenceSpec) ? $referenceSpec : [$referenceSpec]);
        $ref = $ref !== null ? trim((string) $ref) : null;

        if ($ref === null || $ref === '') {
            return; // ohne Referenz keine Variante
        }

        $variationSkuPrefix = (string) ($this->flag('variation_sku_prefix', '') ?? '');
        $variationSku = $variationSkuPrefix !== '' ? $variationSkuPrefix . $ref : $ref;

        // 1) Payload aus variation_fields
        // Unterstützte Formate:
        // - 'field' => 'CSV-Spalte'
        // - 'field' => ['CSV-Spalte 1', 'CSV-Spalte 2']
        // - 'field' => [
        //       'columns'   => 'CSV-Spalte' ODER ['CSV-Spalte 1', 'CSV-Spalte 2'],
        //       'transform' => Closure ODER [Class::class, 'method'],
        //   ]
        $variationPayload = [];
        $variationFieldsMapping = $this->mapping['variation_fields'] ?? [];

        foreach ($variationFieldsMapping as $dbField => $csvSpec) {
            $rawValue = null;

            // Neue strukturierte Variante: ['columns' => ..., 'transform' => ...]
            if (is_array($csvSpec) && (array_key_exists('columns', $csvSpec) || array_key_exists('column', $csvSpec))) {
                $cols = $csvSpec['columns'] ?? $csvSpec['column'];
                $candidates = is_array($cols) ? $cols : [$cols];
                $rawValue = $this->cell($row, $candidates);
            } else {
                // Alte Variante: String ODER Array von Strings
                $candidates = is_array($csvSpec) ? $csvSpec : [$csvSpec];
                $rawValue = $this->cell($row, $candidates);
            }

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            // Basiswert ggf. trimmen, wenn String
            $finalValue = is_string($rawValue) ? trim($rawValue) : $rawValue;

            // Optional: Transform aus dem Mapping anwenden (nur bei strukturiertem Mapping)
            if (is_array($csvSpec) && array_key_exists('transform', $csvSpec)) {
                $transform = $csvSpec['transform'];

                // Variante: [ClassName::class, 'method']
                if (is_array($transform) && count($transform) === 2) {
                    [$class, $method] = $transform;
                    if (class_exists($class) && method_exists($class, $method)) {
                        $finalValue = $class::$method($finalValue, $row);
                    }
                }

                // Variante: Closure
                if ($transform instanceof \Closure) {
                    $finalValue = $transform($finalValue, $row);
                }
            }

            $variationPayload[$dbField] = $finalValue;
        }

        // 2) Variante erstellen oder aktualisieren (bestehende Logik beibehalten)
        $variation = ProductVariation::updateOrCreate(
            ['product_id' => $product->id, 'sku' => $variationSku],
            $variationPayload
        );

        // Attribute zuweisen
        $this->handleVariationAttributes($variation, $row);

        // Persist computed parent name (variable products depend on variation attributes)
        $this->persistComputedProductName($product);
    }

    /**
     * Liest Varianten-Attribute aus der CSV-Zeile und verknüpft deren Werte
     * mit der Variante (Pivot-Tabelle).
     *
     * @param  \App\Models\ProductVariation  $variation
     * @param  array<string,mixed>           $row
     * @return void
     */
    protected function handleVariationAttributes(\App\Models\ProductVariation $variation, array $row): void
    {
        $attributeValueIds = [];
        $variationMapping  = $this->mapping['variation'] ?? [];

        if ($this->isPetzlVariationRow($row)) {

            $attributeValueIds = array_merge(
                $attributeValueIds,
                $this->resolvePetzlVariationAttributeValueIds($row)
            );
        } else {
            foreach ($variationMapping as $attributeName => $csvSpec) {
                $value = $this->cell($row, is_array($csvSpec) ? $csvSpec : [$csvSpec]);
                if ($value === null || $value === '') {
                    continue;
                }

                $attributeValue = $this->firstOrCreateAttributeValue((string) $attributeName, (string) $value);
                $attributeValueIds[] = $attributeValue->id;
            }
        }

        if (! empty($attributeValueIds)) {
            $variation->attributeValues()->sync(array_values(array_unique($attributeValueIds)));
        }

        if ($this->flag('auto_attribute_groups', false) === true) {
            foreach ($row as $colName => $raw) {
                if (!is_string($colName)) {
                    continue;
                }

                $colNameClean = trim($colName);
                if (!str_starts_with($colNameClean, 'Attribute Group:')) {
                    continue;
                }

                $value = is_string($raw) ? trim($raw) : (string) $raw;
                $value = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $value) ?? $value;
                $value = trim($value);

                if ($value === '') {
                    continue;
                }

                $attributeDisplayName = trim(substr($colNameClean, strlen('Attribute Group:')));
                $attributeDisplayName = preg_replace('/\s+/u', ' ', $attributeDisplayName) ?? $attributeDisplayName;
                $attributeDisplayName = trim($attributeDisplayName);

                if ($attributeDisplayName === '') {
                    continue;
                }

                if (str_contains($attributeDisplayName, '|')) {
                    ProductMeta::updateOrCreate(
                        [
                            'product_id'   => $variation->product_id,
                            'variation_id' => $variation->id,
                            'scope'        => 'variation',
                            'key'          => 'attribute_group_raw.' . Str::slug($attributeDisplayName, '_'),
                        ],
                        ['value' => $value]
                    );
                    continue;
                }

                $attributeValue = $this->firstOrCreateAttributeValue($attributeDisplayName, $value);
                $attributeValueIds[] = $attributeValue->id;
            }

            if (! empty($attributeValueIds)) {
                $variation->attributeValues()->sync(array_values(array_unique($attributeValueIds)));
            }
        }
    }

    /**
     * Prüft, ob eine CSV-Zeile aus dem Petzl-Import stammt.
     *
     * Grundlage:
     * - typische Petzl-Spalten wie "Product name", "Reference"
     * - sowie vorhandene Specifications-Spalten
     *
     * @param array<string, mixed> $row
     * @return bool
     */
    protected function isPetzlVariationRow(array $row): bool
    {
        return array_key_exists('Product name', $row)
            && array_key_exists('Reference', $row)
            && (
                array_key_exists('Specifications', $row)
                || array_key_exists('Specifications_2', $row)
            );
    }

    /**
     * Ermittelt Attributwerte (z. B. size, color) für eine Petzl-Variante
     * aus den Specifications-Spalten.
     *
     * Hintergrund:
     * - Petzl nutzt zusammengeführte Excel-Spalten
     * - daraus entstehen "Specifications" und "Specifications_2"
     * - die Werte können je nach Produkt links oder rechts stehen
     *
     * @param array<string, mixed> $row
     * @return array<int, int> Liste von ProductAttributeValue IDs
     */
    protected function resolvePetzlVariationAttributeValueIds(array $row): array
    {
        $ids = [];

        $specCandidates = [
            $row['Specifications'] ?? null,
            $row['Specifications_2'] ?? null,
        ];

        $specValues = [];
        foreach ($specCandidates as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $specValues[] = $value;
        }

        foreach ($specValues as $value) {
            if ($this->looksLikePetzlSize($value)) {
                $ids[] = $this->firstOrCreateAttributeValue('size', $value)->id;
                continue;
            }

            $ids[] = $this->firstOrCreateAttributeValue('color', $value)->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Heuristik zur Erkennung von Größenangaben bei Petzl.
     *
     * Erkennt u. a.:
     * - S, M, L, XL, XXL
     * - numerische Größen (0, 1, 2, ...)
     *
     * @param string $value
     * @return bool
     */
    protected function looksLikePetzlSize(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (preg_match('/^(XXS|XS|S|M|L|XL|XXL)$/i', $value)) {
            return true;
        }

        if (preg_match('/^\d+$/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Erstellt (oder findet) ein Attribut und dessen Wert.
     *
     * Kapselt die Standardlogik für:
     * - ProductAttribute
     * - ProductAttributeValue
     *
     * @param string $attributeDisplayName
     * @param string $value
     * @return \App\Models\ProductAttributeValue
     */
    protected function firstOrCreateAttributeValue(string $attributeDisplayName, string $value): \App\Models\ProductAttributeValue
    {
        $attributeDisplayName = trim($attributeDisplayName);
        $attributeDisplayNameResolved = $this->resolveAttributeDisplayName($attributeDisplayName);
        $value = trim($value);

        $attributeSlug = \Illuminate\Support\Str::slug($attributeDisplayName);

        $attribute = \App\Models\ProductAttribute::firstOrCreate(
            ['slug' => $attributeSlug],
            ['name' => $attributeDisplayNameResolved]
        );

        if ($attribute->name !== $attributeDisplayNameResolved) {
            $attribute->name = $attributeDisplayNameResolved;
            $attribute->save();
        }

        return \App\Models\ProductAttributeValue::firstOrCreate(
            ['attribute_id' => $attribute->id, 'slug' => \Illuminate\Support\Str::slug($value)],
            ['value' => $value]
        );
    }

    /**
     * Gibt den fachlichen Anzeigenamen für ein Attribut zurück.
     *
     * @param string $attributeDisplayName
     * @return string
     */
    protected function resolveAttributeDisplayName(string $attributeDisplayName): string
    {
        return match (trim(mb_strtolower($attributeDisplayName))) {
            'size' => 'Größe',
            'color' => 'Farbe',
            default => $attributeDisplayName,
        };
    }

    /**
     * Normalisiert CSV-Headernamen robust.
     *
     * @param string $header
     * @param bool $toLower
     * @return string
     */
    protected function normalizeHeader(string $header, bool $toLower = true): string
    {
        // BOM am Anfang entfernen
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        // Zeilenumbrüche ersetzen
        $s = str_replace(["\r", "\n"], ' ', $s);

        // Steuerzeichen / NBSP / FEFF entfernen
        $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $s) ?? $s;

        // Mehrfach-Spaces vereinheitlichen
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        $s = trim($s);

        return $toLower ? mb_strtolower($s) : $s;
    }

    /**
     * Normalisiert einen CSV-Zellwert robust.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function normalizeCellValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $s = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $s = str_replace(["\r", "\n"], ' ', $s);
        $s = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * Liefert den ersten nicht-leeren Zellwert aus einer CSV-Zeile anhand einer Kandidatenliste
     * von Spaltennamen. Berücksichtigt unterschiedliche Schreibweisen/Umlaute/Spaces dank
     * Header-Normalisierung.
     *
     * Beispiel:
     *   firstNonEmptyFromRow($row, ['Farbe Bezeichnung','Farb-Code','Farbcode','Farbe'])
     *
     * @param array<string,mixed> $row         Assoziatives Array (CSV-Zeile)
     * @param array<int,string>   $candidates  Mögliche Spaltennamen (in Priorität)
     * @return ?string                         Erster gefundener, getrimmter Wert oder null
     */
    protected function firstNonEmptyFromRow(array $row, array $candidates): ?string
    {
        if (empty($row) || empty($candidates)) {
            return null;
        }

        // Map: normalisierter Header -> Original-Header
        $keyMap = [];
        foreach (array_keys($row) as $key) {
            $keyMap[$this->normalizeHeader((string)$key)] = $key;
        }

        foreach ($candidates as $cand) {
            $normCand = $this->normalizeHeader((string)$cand);

            // 1) bevorzugt über normalisierte Header-Map
            if (isset($keyMap[$normCand])) {
                $val = $row[$keyMap[$normCand]] ?? null;
                if (is_string($val)) {
                    $val = trim($val);
                    // erneut unsichtbare Zeichen entfernen
                    $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
                }
                if ($val !== null && $val !== '') {
                    return (string)$val;
                }
            }

            // 2) Fallback: direkter Zugriff (falls Key exakt passt)
            if (array_key_exists($cand, $row)) {
                $val = $row[$cand];
                if (is_string($val)) {
                    $val = trim($val);
                    $val = preg_replace('/[\x{00}-\x{1F}\x{7F}\x{A0}\x{FEFF}]/u', '', $val) ?? $val;
                }
                if ($val !== null && $val !== '') {
                    return (string)$val;
                }
            }
        }

        return null;
    }

    /**
     * Lädt das Mapping entweder aus config('import_mappings.<name>')
     * oder aus config/import_mappings/<name>.php (Datei muss ein Array returnen).
     *
     * @throws \RuntimeException wenn nichts gefunden.
     */
    protected function loadMapping(string $name): array
    {
        $fromConfig = config("import_mappings." . $name);
        if (is_array($fromConfig)) {
            return $fromConfig;
        }

        $path = base_path("config/import_mappings/{$name}.php");
        if (is_file($path)) {
            $map = require $path;
            if (! is_array($map)) {
                throw new \RuntimeException("Mapping file {$path} must return an array.");
            }
            return $map;
        }

        throw new \RuntimeException("Mapping '{$name}' not found via config() or file {$path}");
    }

    /**
     * Berechnet und persistiert den Produktnamen auf Basis der aktuellen
     * Produkt- und Varianten-Daten.
     *
     * Wichtig:
     * Die Varianten-Relation wird bewusst frisch geladen, da diese Methode
     * während des Imports mehrfach pro Produkt aufgerufen wird und bereits
     * geladene Relations sonst veraltete Daten enthalten können.
     *
     * @param \App\Models\Product $product
     * @return void
     */
    protected function persistComputedProductName(\App\Models\Product $product): void
    {
        $product->refresh()->load([
            'manufacturer',
            'variations.attributeValues.attribute',
        ]);

        $ctx = \App\Services\ProductNaming\ProductNameContext::fromProduct($product);

        /** @var \App\Services\ProductNaming\ProductNameUpdater $updater */
        $updater = app(\App\Services\ProductNaming\ProductNameUpdater::class);

        $updater->update($product);
    }
}
