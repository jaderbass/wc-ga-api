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
use App\Support\ImportLog;

/**
 * Hersteller-Importer für Edelrid (CSV).
 *
 * Lädt das Edelrid-spezifische Mapping und führt den CSV-Import aus.
 * Unterstützt sowohl Pfad-basierte Importe als auch Upload-Objekte.
 */
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
  }

  /**
   * Verarbeitet eine hochgeladene CSV-Datei (konsistent zum Petzl-Importer).
   *
   * @param UploadedFile $file
   * @return void
   */
  public function handleUploadedFile(UploadedFile $file): void
  {
    ImportLog::debug('ImporterForEdelrid.handleUploadedFile ENTER', [
      'mapping_keys' => array_keys($this->mapping ?? []),
    ]);

    ImportLog::debug('ImporterForEdelrid mapping keys', [
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
   * CSV-Import per Pfad starten.
   *
   * @param  string  $path  Absoluter Pfad zur CSV-Datei
   * @return void
   */
  public function import(string $filePath): void
  {
    // HARTES ENFORCEMENT direkt vor dem eigentlichen Import.
    $this->mapping = config('import_mappings.edelrid', []);

    ImportLog::debug('Importer import() enforcing mapping', [
      'class'          => static::class,
      'manufacturer_id' => $this->manufacturerId,
      'product_map'    => $this->mapping['product']          ?? null,
      'variation_map'  => $this->mapping['variation']        ?? null,
      'var_fields_map' => $this->mapping['variation_fields'] ?? null,
    ]);

    // Jetzt die eigentliche Import-Logik der Elternklasse ausführen.
    parent::import($filePath);
  }

  /**
   * Transformiert die Roh-Maßangabe in strukturierte Millimeter-Werte.
   *
   * Erwartete Formate z. B.:
   * - "10 x 20 x 30 cm"
   * - "10x20x30mm"
   * - "10 / 20 / 30 cm"
   *
   * Rückgabe:
   * - null, wenn nichts Sinnvolles geparst werden konnte
   * - assoziatives Array mit Keys:
   *   - dimensions_raw
   *   - dimension_length_mm (optional)
   *   - dimension_width_mm  (optional)
   *   - dimension_height_mm (optional)
   *
   * @param string|null              $value Rohwert aus der CSV (eine Spalte)
   * @param array<string,mixed>      $row   gesamte CSV-Zeile (falls später mehr Kontext nötig ist)
   * @return array<string,int|string>|null
   */
  public static function transformDimensions(?string $value, array $row): ?array
  {
    if ($value === null) {
      return null;
    }

    $raw = trim($value);
    if ($raw === '') {
      return null;
    }

    // Einheit bestimmen (Default mm, bei "cm" → später in mm umrechnen)
    $unit  = 'mm';
    $lower = mb_strtolower($raw);
    if (str_contains($lower, 'cm')) {
      $unit = 'cm';
    }

    // Nur Zahlen, Komma, Punkt, x, *, / und Leerzeichen behalten
    $clean = preg_replace('/[^0-9,.\sxX*\/]/u', '', $raw) ?? '';

    // Auf x, *, / splitten
    $parts = preg_split('/[xX*\/]/', $clean);

    $numbers = [];
    foreach ($parts as $part) {
      $part = trim($part);
      if ($part === '') {
        continue;
      }

      // Dezimal-Komma in Punkt umwandeln
      $part = str_replace(',', '.', $part);
      if (! is_numeric($part)) {
        continue;
      }

      $num = (float) $part;

      // cm → mm
      if ($unit === 'cm') {
        $num *= 10;
      }

      $numbers[] = (int) round($num);
    }

    // Wenn wir gar nichts parsen konnten → nur Rohwert zurückgeben
    if ($numbers === []) {
      return [
        'dimensions_raw' => $raw,
      ];
    }

    $result = [
      'dimensions_raw' => $raw,
    ];

    if (isset($numbers[0])) {
      $result['dimension_length_mm'] = $numbers[0];
    }
    if (isset($numbers[1])) {
      $result['dimension_width_mm'] = $numbers[1];
    }
    if (isset($numbers[2])) {
      $result['dimension_height_mm'] = $numbers[2];
    }

    return $result;
  }
}
