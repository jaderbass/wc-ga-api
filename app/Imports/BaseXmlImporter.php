<?php

namespace App\Imports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;

abstract class BaseXmlImporter
{
    abstract protected function model(): string;
    abstract protected function fixedValues(): array;
    abstract protected function mapXmlItem(SimpleXMLElement $item): array;

    public function importFromFile(string $path): void
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));
        $this->importXml($xml);
    }

    public function importFromUrl(string $url): void
    {
        $response = Http::get($url);

        if (! $response->ok()) {
            Log::error("Fehler beim Abruf des XML-Feeds", ['url' => $url, 'status' => $response->status()]);
            return;
        }

        $xml = simplexml_load_string($response->body());
        $this->importXml($xml);
    }

    protected function importXml(SimpleXMLElement $xml): void
    {
        foreach ($xml->product as $productNode) {
            try {
                $data = $this->mapXmlItem($productNode);
                $data = array_merge($data, $this->fixedValues());

                $model = $this->model();

                $record = $model::where('productnumber', $data['productnumber'] ?? null)->first();
                if ($record) {
                    $record->update($data);
                    Log::info("Produkt aktualisiert", ['id' => $record->id]);
                } else {
                    $model::create($data);
                    Log::info("Produkt erstellt", ['productnumber' => $data['productnumber']]);
                }
            } catch (\Throwable $e) {
                Log::error("Fehler beim Verarbeiten eines Produkts", [
                    'exception' => $e->getMessage(),
                    'trace' => Str::limit($e->getTraceAsString(), 500),
                    'data' => (string) $productNode->asXML(),
                ]);
            }
        }
    }
}
