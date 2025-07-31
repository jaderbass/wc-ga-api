<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Basisklasse für XML-Importe.
 *
 * Liest XML-Dateien (lokal oder von einer URL), parst deren Inhalt und speichert
 * die extrahierten Daten in der Datenbank. Hersteller-Importer erben von dieser Klasse
 * und implementieren die spezifische Logik zum Extrahieren der Daten.
 */
abstract class BaseXmlImporter
{
    /**
     * Gibt den Eloquent-Modellklassennamen zurück, auf den der Import zielt.
     *
     * @return string Vollqualifizierter Klassenname (z. B. App\\Models\\Product::class)
     */
    abstract protected function model(): string;

    /**
     * Verarbeitet eine hochgeladene XML-Datei.
     *
     * @param string $path Relativer Pfad zur XML-Datei im Storage.
     * @return void
     */
    public function handleUploadedXmlFile(string $path): void
    {
        $this->handleFromPath($path);
    }

    /**
     * Verarbeitet eine XML-Datei von einer API-URL.
     *
     * @param string $url Vollständige URL der XML-Quelle.
     * @return void
     */
    public function handleFromUrl(string $url): void
    {
        $response = Http::get($url);

        if (!$response->ok()) {
            Log::error("Fehler beim Abrufen der XML-URL: {$url}", [
                'status' => $response->status(),
            ]);
            return;
        }

        $tmpPath = storage_path('app/imports/feed-' . uniqid() . '.xml');
        file_put_contents($tmpPath, $response->body());

        $relativePath = str_replace(storage_path('app/'), '', $tmpPath);
        $this->handleFromPath($relativePath);
    }

    /**
     * Liest und verarbeitet eine XML-Datei von einem lokalen Pfad.
     *
     * @param string $path Relativer Pfad zur XML-Datei im Storage.
     * @return void
     */
    protected function handleFromPath(string $path): void
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));

        if (!$xml) {
            Log::error("Fehler beim Laden der XML-Datei", ['path' => $path]);
            return;
        }

        $records = $this->parseXml($xml);

        foreach ($records as $record) {
            $this->upsertRecord($record);
        }

        Log::info("XML-Import abgeschlossen", ['file' => $path, 'count' => count($records)]);
    }

    /**
     * Parst den XML-Inhalt und gibt ein Array von Datensätzen zurück.
     *
     * Muss von der abgeleiteten Klasse implementiert werden.
     *
     * @param \SimpleXMLElement $xml XML-Dokument.
     * @return array Array mit Datensätzen für die Datenbank.
     */
    abstract protected function parseXml(\SimpleXMLElement $xml): array;

    /**
     * Erstellt oder aktualisiert einen Datensatz in der Datenbank.
     *
     * @param array $data Bereinigte Daten für das Modell.
     * @return void
     */
    protected function upsertRecord(array $data): void
    {
        $model = $this->model();
        $record = $model::where('productnumber', $data['productnumber'] ?? null)->first();

        if ($record) {
            $record->update($data);
            Log::info("Produkt aktualisiert", ['id' => $record->id]);
        } else {
            $model::create($data);
            Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
        }
    }
}
