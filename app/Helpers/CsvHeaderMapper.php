<?php

namespace App\Helpers;

class CsvHeaderMapper
{
  /**
   * Mappt die Schlüssel des Arrays von CSV-Headern auf Datenbank-Spalten
   *
   * @param array $row
   * @param array $mapping z. B. ['productname' => 'DESCRIPTION']
   *
   * @return array
   */
  public static function remap(array $row, array $mapping): array
  {
    $mapped = [];

    foreach ($mapping as $to => $from) {
      $mapped[$to] = $row[$from] ?? null;
    }

    return $mapped;
  }
}
