<?php

namespace App\Imports\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\PetzlCategoryMapping;
use App\Services\Petzl\PetzlDescriptionImportService;
use App\Jobs\SyncPetzlDescriptionJob;
use Illuminate\Support\Collection;

/**
 * Importer für Petzl-Produktdaten (CSV), unterstützt variable Produkte.
 *
 * Erweitert den GenericCsvProductImporter und verwendet ein spezifisches Mapping.
 * Gruppiert Zeilen zu Hauptprodukten und legt Varianten an.
 */

class ImporterForPetzl extends GenericCsvProductImporter
{
  /**
   * Konstruktor für den Petzl-Importer.
   *
   * @param  int  $manufacturerId  ID des Petzl-Herstellers (z.B. 4).
   */
  public function __construct(int $manufacturerId)
  {
    // Ruft den Konstruktor der Elternklasse auf und übergibt
    // den Namen der Mapping-Datei und die Hersteller-ID.
    // 'petzl' verweist auf config/import_mappings/petzl.php.
    parent::__construct('petzl', $manufacturerId);
  }

  /**
   * Behandelt eine hochgeladene Petzl-CSV-Datei.
   *
   * Wir erzeugen eine separate, normalisierte CSV-Datei mit eindeutigen Header-Namen
   * (z.B. "Unit", "Unit_2", "Unit_3", ...), und übergeben diese dann an den
   * generischen Importer.
   */
  public function handleUploadedFile(string $filePath): void
  {
    Log::info('Petzl-Import gestartet (CSV normalisieren).', [
      'file' => $filePath,
      'manufacturer_id' => $this->manufacturerId,
    ]);

    $normalizedRelativePath = $this->createNormalizedCsvForPetzl($filePath);

    Log::info('Petzl-Import: verwende normalisierte CSV.', [
      'original'   => $filePath,
      'normalized' => $normalizedRelativePath,
    ]);

    // Die eigentliche Import-Logik wird von der Elternklasse gehandhabt.
    $this->import($normalizedRelativePath);

    Log::info('Petzl-Import abgeschlossen.');
  }

  /**
   * Überschreibt den generischen Import-Prozess für Petzl.
   *
   * - Liest die Original-CSV ein
   * - erzeugt eine zweite CSV mit eindeutigen Header-Namen
   *   (z.B. "Unit", "Unit_2", "Unit_3", "" -> "column_10", ...)
   * - ruft dann den generischen Import auf dieser normalisierten Datei auf.
   *
   * @param  string  $filePath  Pfad, den der ImporterSelector übergibt
   */
  public function import(string $filePath): void
  {
    Log::info('Petzl-Import: starte Header-Normalisierung', [
      'original_path' => $filePath,
      'manufacturer_id' => $this->manufacturerId,
    ]);

    $normalizedPath = $this->createNormalizedCsvForPetzl($filePath);

    Log::info('Petzl-Import: verwende normalisierte CSV für Import', [
      'normalized_path' => $normalizedPath,
    ]);

    // Generischen Import mit der normalisierten Datei ausführen
    parent::import($normalizedPath);

    // Aufräumen, falls es wirklich eine neue Datei war
    if ($normalizedPath !== $filePath && is_file($normalizedPath)) {
      @unlink($normalizedPath);
    }
  }

  /**
   * Erzeugt eine normalisierte Kopie der CSV mit eindeutigen Header-Namen.
   *
   * - Löscht keine Spalten
   * - Jeder Header wird eindeutig gemacht:
   *   - ""        -> "column_0", "column_5", ...
   *   - "Unit"    -> "Unit", "Unit_2", "Unit_3", ...
   *   - "Status"  -> "Status", "Status_2", ...
   *
   * @param  string  $filePath  Pfad zur Original-CSV (wie vom ImporterSelector übergeben)
   * @return string Pfad zur normalisierten CSV (oder Originalpfad bei Fehler)
   */
  protected function createNormalizedCsvForPetzl(string $filePath): string
  {
    if (!is_file($filePath) || !is_readable($filePath)) {
      Log::warning('Petzl-Import: Original-CSV nicht lesbar, benutze Fallback.', [
        'file' => $filePath,
      ]);

      return $filePath;
    }

    $in = fopen($filePath, 'rb');

    if (!$in) {
      Log::warning('Petzl-Import: Original-CSV konnte nicht geöffnet werden.', [
        'file' => $filePath,
      ]);

      return $filePath;
    }

    // Header einlesen (Semikolon-CSV, fgetcsv kann Zeilenumbrüche in Quotes)
    $header = fgetcsv($in, 0, ';');

    if (!is_array($header)) {
      fclose($in);
      Log::warning('Petzl-Import: CSV-Header konnte nicht gelesen werden.', [
        'file' => $filePath,
      ]);

      return $filePath;
    }

    // Header-Namen eindeutig machen
    $seen = [];
    $cleanHeader = [];

    foreach ($header as $idx => $rawName) {
      // Zeilenumbrüche im Header entfernen, damit z.B.
      // "Product  packed\nweight" → "Product  packed weight" wird.
      $cleanRaw = str_replace(["\r", "\n"], ' ', (string) $rawName);
      $name = trim($cleanRaw);

      if ($name === '') {
        $name = 'column_' . $idx;
      }

      if (!isset($seen[$name])) {
        $seen[$name] = 1;
        $cleanHeader[] = $name;
      } else {
        $seen[$name]++;
        $cleanHeader[] = $name . '_' . $seen[$name];
      }
    }

    Log::info('Petzl-Import: Header normalisiert.', [
      'file' => $filePath,
      'seen_counts' => $seen,
    ]);

    // Neue Datei im gleichen Verzeichnis: foo.csv -> foo.normalized.csv
    $dir = dirname($filePath);
    $base = pathinfo($filePath, PATHINFO_FILENAME);
    $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'csv';

    $normalizedPath = $dir . '/' . $base . '.normalized.' . $ext;

    $out = fopen($normalizedPath, 'wb');

    if (!$out) {
      fclose($in);
      Log::warning('Petzl-Import: normalisierte CSV konnte nicht geschrieben werden.', [
        'normalized_path' => $normalizedPath,
      ]);

      return $filePath;
    }

    // neuen Header schreiben
    fputcsv($out, $cleanHeader, ';');

    // restliche Zeilen unverändert kopieren
    while (($row = fgetcsv($in, 0, ';')) !== false) {
      fputcsv($out, $row, ';');
    }

    fclose($in);
    fclose($out);

    return $normalizedPath;
  }

    /**
     * Stößt nach dem Produktimport den Petzl-Beschreibungsimport an.
     *
     * Die Beschreibung wird nur nachgeladen, wenn noch keine automatische
     * Beschreibung vorhanden ist und keine manuelle Beschreibung geschützt wird.
     *
     * @param Product $product Importiertes oder aktualisiertes Produkt.
     * @param Collection<int, array<string, mixed>> $rows CSV-Zeilen der Produktgruppe.
     * @param array<string, mixed> $productPayload Aufbereitete Produktdaten aus dem Mapping.
     */
    protected function afterProductUpserted(
        Product $product,
        Collection $rows,
        array $productPayload
    ): void {
        $rows->each(function (array $row): void {
            $this->ensureCategoryMapping($row);
        });

        $importService = app(PetzlDescriptionImportService::class);

        if (! $importService->shouldImport($product)) {
            return;
        }

        $productName = $productPayload['product_name']
            ?? $rows->first()['Product name']
            ?? null;

        if (! $productName) {
            Log::warning('Petzl description sync skipped: missing product name.', [
                'product_id' => $product->id,
            ]);

            return;
        }

        $firstRow = $rows->first() ?? [];

        Log::info('Dispatching Petzl description job with category mapping.', [
            'product_id' => $product->id,
            'product_name' => $productName,
            'category' => $firstRow['Category'] ?? null,
            'subcategory' => $firstRow['Subcategory'] ?? null,
        ]);

        SyncPetzlDescriptionJob::dispatch(
            productId: $product->id,
            productName: (string) $productName,
            force: false,
            sourceCategory: (string) ($firstRow['Category'] ?? ''),
            sourceSubcategory: (string) ($firstRow['Subcategory'] ?? ''),
        )->onQueue('imports');

        Log::info('Petzl description sync job dispatched.', [
            'product_id' => $product->id,
            'product_name' => $productName,
        ]);
    }

    /**
     * Stellt sicher, dass die Petzl-Kategorie/Subkategorie aus der CSV
     * als Mapping-Datensatz vorhanden ist.
     *
     * Fehlende Übersetzungen werden bewusst leer gelassen und später
     * über Filament gepflegt.
     *
     * @param array<string, mixed> $row CSV-Zeile aus dem Petzl-Import.
     */
    protected function ensureCategoryMapping(array $row): ?PetzlCategoryMapping
    {
        $category = trim((string) ($row['Category'] ?? ''));
        $subcategory = trim((string) ($row['Subcategory'] ?? ''));

        if ($category === '') {
            return null;
        }

        return PetzlCategoryMapping::firstOrCreate(
            [
                'source_category' => $category,
                'source_subcategory' => $subcategory !== '' ? $subcategory : null,
            ],
            [
                'is_reviewed' => false,
                'is_active' => true,
            ],
        );
    }
}
