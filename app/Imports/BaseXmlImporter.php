<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;

abstract class BaseXmlImporter
{
    /**
     * Muss das zu importierende Model zurückgeben.
     */
    abstract protected function model(): string;

    /**
     * Muss feste Werte (z. B. manufacturer_id) zurückgeben.
     */
    abstract protected function fixedValues(): array;

    /**
     * Muss aus einem SimpleXMLElement ein assoziatives Array machen.
     */
    abstract protected function parseXmlItem(SimpleXMLElement $entry): array;

    /**
     * Muss einen Datensatz upserten (update or create).
     */
    abstract protected function upsertRecord(array $data): void;

    public function handleUploadedXmlFile(string $path): int
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));
        if ($xml === false) {
            Log::error("Fehler beim Laden der XML-Datei: {$path}");
            return 0;
        }

        $count = 0;

        foreach ($xml->PRODUCTS->PRODUCTITEM as $entry) {
            try {
                $data = $this->parseXmlItem($entry);
                $data = array_merge($data, $this->fixedValues());

                $this->upsertRecord($data);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Fehler beim XML-Import", [
                    'exception' => $e->getMessage(),
                    'entry' => json_encode($entry),
                ]);
            }
        }

        Log::info("XML-Import abgeschlossen: {$count} Datensätze verarbeitet.");
        return $count;
    }

    public function handleFromUrl(string $url, ?string $user = null, ?string $password = null): int
    {
        $response = Http::withBasicAuth($user, $password)->get($url);

        if (! $response->ok()) {
            Log::error("Fehler beim Abrufen der XML-URL: {$url}", [
                'status' => $response->status(),
            ]);
            return 0;
        }

        $tmpPath = storage_path('app/imports/feed-' . uniqid() . '.xml');
        file_put_contents($tmpPath, $response->body());

        $relativePath = str_replace(storage_path('app/'), '', $tmpPath);
        return $this->handleUploadedXmlFile($relativePath);
    }
}
