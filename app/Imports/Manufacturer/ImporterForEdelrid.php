<?php

/**
 * @package   App\Importers\Manufacturer
 * @author    GeoAlpin
 * @license   Proprietary
 *
 * Importer für Hersteller "Edelrid".
 *
 * Verantwortlichkeiten:
 * - Erzwingt das Edelrid-Mapping (config/import_mappings/edelrid.php).
 * - Verknüpft Produkte mit der übergebenen manufacturer_id.
 * - Bietet eine handleUploadedFile()-Methode analog zum Petzl-Importer.
 */

namespace App\Imports\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Importers\Contracts\CsvImporterContract;
use App\Importers\Contracts\HandlesUploadedFile;

class ImporterForEdelrid extends GenericCsvProductImporter implements CsvImporterContract, HandlesUploadedFile
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
   * Konstruktor.
   *
   * @param int $manufacturerId ID des Herstellers (aus dem Select im Backend)
   */
  public function __construct(int $manufacturerId)
  {
    $this->manufacturerId = $manufacturerId;

    logger()->debug('ImporterForEdelrid constructed');
    // Mapping EXPLIZIT setzen – keine Header-Auto-Erkennung.
    $this->mapping = config('import_mappings.edelrid', []);

    Log::debug('Importer constructed', [
      'class'            => static::class,
      'manufacturer_id'  => $this->manufacturerId,
      'mapping_loaded'   => !empty($this->mapping),
      'product_keys'     => array_keys($this->mapping['product'] ?? []),
      'variation_fields' => array_keys($this->mapping['variation_fields'] ?? []),
      'variation_keys'   => array_keys($this->mapping['variation'] ?? []),
    ]);
  }

  /**
   * Verarbeitet eine hochgeladene CSV-Datei (konsistent zum Petzl-Importer).
   *
   * @param UploadedFile $file
   * @return void
   */
  public function handleUploadedFile(UploadedFile $file): void
  {
    Log::debug('ImporterForEdelrid.handleUploadedFile ENTER', [
      'mapping_keys' => array_keys($this->mapping ?? []),
    ]);
    
    Log::debug('ImporterForEdelrid mapping keys', [
      'keys' => array_keys($this->mapping),
    ]);
    
    $filename = uniqid('edelrid_', true) . '.csv';
    $stored   = $file->storeAs('imports', $filename);

    Log::info('Import gestartet', [
      'class'           => static::class,
      'manufacturer_id' => (string) $this->manufacturerId,
      'sourceType'      => 'csv',
      'source'          => $stored,
    ]);

    $path = Storage::path($stored);
    $this->import($path);
  }

  /**
   * Überschreibt import(), um das Edelrid-Mapping UNMITTELBAR vor dem Import
   * nochmal zu erzwingen (falls es irgendwo unterwegs überschrieben wurde).
   *
   * @param string $filePath
   * @return void
   */
  public function import(string $filePath): void
  {
    // HARTES ENFORCEMENT direkt vor dem eigentlichen Import.
    $this->mapping = config('import_mappings.edelrid', []);

    Log::debug('Importer import() enforcing mapping', [
      'class'          => static::class,
      'manufacturer_id' => $this->manufacturerId,
      'product_map'    => $this->mapping['product']          ?? null,
      'variation_map'  => $this->mapping['variation']        ?? null,
      'var_fields_map' => $this->mapping['variation_fields'] ?? null,
    ]);

    // Jetzt die eigentliche Import-Logik der Elternklasse ausführen.
    parent::import($filePath);
  }
}
