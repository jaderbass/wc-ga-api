<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseCsvImporter;

/**
 * Importer für Hersteller Aliens.
 *
 * Erbt von BaseCsvImporter und definiert das Feld-Mapping sowie feste Werte
 * für Produkte des Herstellers Aliens.
 */
class ImporterForAliens extends BaseCsvImporter
{
    /**
     * Gibt das Modell zurück, in dem die Daten gespeichert werden.
     *
     * @return string Vollqualifizierter Klassenname des Zielmodells.
     */
    protected function model(): string
    {
        return \App\Models\Product::class;
    }

    /**
     * Gibt ein Mapping von Datenbankfeldern zu CSV-Spalten zurück.
     *
     * @return array Assoziatives Array im Format [DB-Feld => CSV-Spalte].
     */
    protected function columnMap(): array
    {
        return [
            'productnumber'         => 'ARTICLE', 
            'productname'           => 'NAME', 
            'description'           => 'DESCRIPTION', 
            'shortdescription'      => 'SHORT DESCRIPTION', 
            'eancode'               => 'EAN', 
            'weight'                => 'WEIGHT', 
            'manufacturercountry'   => 'ORIGIN',
        ];
    }

    /**
     * Gibt zusätzliche feste Werte zurück, die beim Import gesetzt werden.
     *
     * @return array Key-Value-Paare fester Werte.
     */
    protected function fixedValues(): array
    {
        return [
            'manufacturer_id' => 5, // Singing Rock
        ];
    }
}