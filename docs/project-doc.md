# Dokumentation GeoAlpin / WooCommerce API

## Wo befinden sich welche Informationen

|Dateipfad|Bemerkung|
|------|------|
|`app/Filament/Components/`|Komponenten, aktuell nur die Passwortverschlüsselung|
|`app/Filament/Imports/`|Importer für die einzelnen Items, aktuell nur ProductImporter.php|
|`app/Filament/Pages/Dashboard.php`|Meta-Informationen für das Dashboard (Icon, Titel, View etc.)|
|`app/Filament/Resources/{ItemName}Resource.php`|Aufbau des jeweiligen Formulars im Dashboard|
|`app/Filament/Resources/{ItemName}Resource/Pages`|Ordner mit den speziellen Dateien für das Anlegen, Bearbeiten und Auflisten des entsprechenden Items|
|`app/Filament/Widgets/`|Die einzelnen Cards für die Dashboard-Übersicht|
|`app/Helpers/`|Ordner mit verschiedenen Hilfsdateien|
|`app/Http/Controllers/`|Controller-Klassen für die Datenbank-Funktionalität|
|`app/Importers/GenericCsvProductImporter.php`|Generelle Logik für den Import von CSV-Dateien, benötigt eine Mapping-Datei!|
|`app/Imports/`|Basis-Importer für die verschiedenen Import-Methoden (CSV, XML, API-URL)|
|`app/Imports/Manufacturer/`|spezielle Importer für die einzelnen Hersteller|
|`config/import_mappings/{HerstellerName}.php`|Die Mapping-Datei für den generischen Importer|
