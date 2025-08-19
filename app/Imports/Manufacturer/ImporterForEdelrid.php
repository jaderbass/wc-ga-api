<?php

namespace App\Importers\Manufacturer;

use App\Importers\GenericCsvProductImporter;
use Illuminate\Support\Facades\Log;

/**
 * ImporterForEdelrid
 *
 * Hersteller-spezifischer CSV-Importer für EDELRID.
 * - Lädt das Mapping "edelrid".
 * - Erzwingt zur Laufzeit korrekte Variations-Felder (Farbe/Größe), falls noch ein altes Mapping (color/size) aktiv wäre.
 *
 * Hintergrund:
 * In Logs wurden Variations-Mappings "color" => "Specifications" und "size" => "Size" sichtbar,
 * obwohl EDELRID die Spalten "Farbe Bezeichnung"/"Farb-Code" und "Größen Bezeichnung"/"Größen Code" liefert.
 * Um sofort korrekte Attribute zu bekommen, setzen wir hier notfalls ein "Hotfix"-Mapping.
 */
class ImporterForEdelrid extends GenericCsvProductImporter
{
  /**
   * Konstruktor – wie bei Petzl, aber mit Mapping-Name "edelrid".
   *
   * @param int|null $manufacturerId
   */
  public function __construct(?int $manufacturerId = null)
  {
    parent::__construct('edelrid', $manufacturerId);

    // --- Hotfix/Fallback: Wenn das geladene Mapping offenbar das falsche Schema nutzt, überschreiben. ---
    $varMap = $this->mapping['variation'] ?? [];

    $hasEnglishKeys = isset($varMap['color']) || isset($varMap['size']);
    $hasWrongCols   = in_array('Specifications', (array)($varMap['color'] ?? []), true) || in_array('Size', (array)($varMap['size'] ?? []), true);

    if ($hasEnglishKeys || $hasWrongCols) {
      $this->mapping['variation'] = [
        'Farbe' => ['Farbe Bezeichnung', 'Farb-Code'],
        'Größe' => ['Größen Bezeichnung', 'Größen Code'],
      ];
      Log::warning('Edelrid: Variation mapping korrigiert (Fallback aktiviert).', [
        'prev_variation_mapping' => $varMap,
        'new_variation_mapping'  => $this->mapping['variation'],
      ]);
    }
  }

  /**
   * Verarbeitung einer hochgeladenen CSV-Datei: nur Logging + Import.
   *
   * @param string $filePath
   * @return void
   */
  public function handleUploadedFile(string $filePath): void
  {
    Log::info('Edelrid-Import gestartet.', [
      'file' => $filePath,
      'manufacturer_id' => $this->manufacturerId,
    ]);

    // Zur Kontrolle: welches Mapping ist jetzt aktiv?
    Log::debug('Edelrid-Mapping aktiv', [
      'variation_mapping_keys' => array_keys($this->mapping['variation'] ?? []),
      'variation_mapping'      => $this->mapping['variation'] ?? null,
    ]);

    $this->import($filePath);

    Log::info('Edelrid-Import abgeschlossen.');
  }
}
