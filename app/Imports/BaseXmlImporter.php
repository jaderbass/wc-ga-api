<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

abstract class BaseXmlImporter extends BaseImporter
{
    /**
     * Lädt XML-Datei und übergibt die geparsten Zeilen an BaseImporter::handle()
     */
    public function handleUploadedXmlFile(string $path): void
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));

        if (! $xml) {
            Log::error("Fehler beim Parsen der XML-Datei: {$path}");
            return;
        }

        $rows = $this->parseXml($xml);

        $this->handle($rows);
    }

    /**
     * Muss vom Kind-Importer implementiert werden:
     * - Gibt ein Array von Zeilen zurück
     */
    abstract protected function parseXml(\SimpleXMLElement $xml): array;

    /**
     * Holt XML-Daten per URL und ruft handleUploadedXmlFile() auf
     */
    public function handleFromUrl(string $url): void
    {
        $response = Http::get($url);

        if (! $response->ok()) {
            Log::error("Fehler beim Abrufen der XML-URL: {$url}", [
                'status' => $response->status(),
            ]);
            return;
        }

        $tmpPath = storage_path('app/imports/feed-' . uniqid() . '.xml');
        file_put_contents($tmpPath, $response->body());

        $relativePath = str_replace(storage_path('app/'), '', $tmpPath);
        $this->handleUploadedXmlFile($relativePath);
    }
}
