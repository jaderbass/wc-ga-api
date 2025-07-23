<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

abstract class BaseXmlImporter
{
    abstract protected function model(): string;

    /**
     * Muss eine Liste normalisierter Arrays zurückgeben
     *
     * @param \SimpleXMLElement $xml
     * @return array<int, array<string, mixed>>
     */
    abstract protected function parseXml(\SimpleXMLElement $xml): array;

    /**
     * Optional: feste Werte wie manufacturer_id etc.
     */
    protected function fixedValues(): array
    {
        return [];
    }

    public function handleUploadedXmlFile(string $path): void
    {
        $this->handleFromPath($path);
    }

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
        $this->handleFromPath($relativePath);
    }

    protected function handleFromPath(string $path): void
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));
        $records = $this->parseXml($xml);

        if (!is_iterable($records)) {
            Log::error('ImporterForSingingRock: parseXml() returned invalid result', [
                'xmlSnippet' => substr($xml->asXML(), 0, 500)
            ]);
            return;
        }

        foreach ($records as $record) {
            try {
                $this->upsertRecord($record);
            } catch (\Throwable $e) {
                Log::error('Fehler beim XML-Import', [
                    'exception' => $e->getMessage(),
                    'record' => $record,
                ]);
            }
        }
    }

    protected function upsertRecord(array $data): void
    {
        Log::debug('→ übergegebene Daten an upsertRecord():', $data);

        if (empty($data['productnumber'])) {
            Log::warning('❗ Kein productnumber gesetzt – Datensatz wird ignoriert', $data);
            return;
        }

        $model = $this->model();

        $record = $model::where('productnumber', $data['productnumber'] ?? null)->first();

        if ($record) {
            $record->update($data);
            Log::info("Produkt aktualisiert", ['id' => $record->id]);
        } else {
            try {
                $model::create($data);
                Log::info("✅ Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
            } catch (\Throwable $e) {
                Log::error("❌ Fehler beim Erstellen des Produkts", [
                    'message' => $e->getMessage(),
                    'data' => $data,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
