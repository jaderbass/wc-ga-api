<?php

/**
 * @package   App\Importers\Manufacturer
 * @author    GeoAlpin
 * @license   Proprietary
 *
 * Importer für Hersteller "Edelrid".
 *
 * Verantwortlichkeiten:
 * - Stellt sicher, dass das Edelrid-Mapping aus config/import_mappings/edelrid.php
 *   verwendet wird (kein Header-Raten).
 * - Nimmt die manufacturer_id entgegen, damit Produkte korrekt verknüpft werden.
 * - Bietet eine konsistente handleUploadedFile()-Methode analog zum Petzl-Importer.
 */

namespace App\Importers\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImporterForEdelrid extends GenericCsvProductImporter
{
  /**
   * Hersteller-ID für die Produktverknüpfung.
   * @var int|null
   */
  protected ?int $manufacturerId = null;

  /**
   * Mapping-Konfiguration für Edelrid.
   * @var array<string,mixed>
   */
  protected array $mapping = [];

  /**
   * Konstruktor
   *
   * @param int $manufacturerId ID des Herstellers (aus dem Select im Backend)
   */
  public function __construct(int $manufacturerId)
  {
    $this->manufacturerId = $manufacturerId;

    // Mapping explizit setzen – keine Auto-Erkennung über Header!
    $this->mapping = config('import_mappings.edelrid', []);

    // Sanity-Check & hilfreiches Logging
    if (empty($this->mapping)) {
      Log::warning('Edelrid-Mapping nicht gefunden oder leer.', [
        'config_key' => 'import_mappings.edelrid',
      ]);
    } else {
      // Minimal prüfen, ob die wichtigsten Keys vorhanden sind
      Log::debug('Edelrid-Mapping geladen.', [
        'has_group_by'   => array_key_exists('group_by', $this->mapping),
        'has_reference'  => array_key_exists('reference', $this->mapping),
        'product_keys'   => array_keys($this->mapping['product'] ?? []),
        'variation_keys' => array_keys($this->mapping['variation'] ?? []),
      ]);
    }
  }

  /**
   * Verarbeitet eine hochgeladene CSV-Datei (konsistent zum Petzl-Importer).
   *
   * Speichert die Datei temporär im Storage und ruft anschließend den
   * CSV-Import auf Basis von GenericCsvProductImporter::import() auf.
   *
   * @param UploadedFile $file
   * @return void
   */
  public function handleUploadedFile(UploadedFile $file): void
  {
    // CSV unter /storage/app/imports ablegen
    $filename = uniqid('edelrid_', true) . '.csv';
    $stored   = $file->storeAs('imports', $filename);

    Log::info('Import gestartet', [
      'manufacturer_id' => (string) $this->manufacturerId,
      'sourceType'      => 'csv',
      'source'          => $stored,
    ]);

    // Absoluten Pfad ermitteln und importieren
    $path = Storage::path($stored);
    $this->import($path);
  }
}
