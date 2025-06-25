<?php

namespace App\Imports;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Helpers\CsvValueSanitizer;

abstract class BaseXmlImporter
{
    abstract protected function model(): string;
    abstract protected function productNodePath(): string;
    abstract protected function mapXmlToData(\SimpleXMLElement $product): array;
    abstract protected function fixedValues(): array;

    public function importFromFile(string $path): void
    {
        $xml = simplexml_load_file(storage_path("app/{$path}"));

        $this->process($xml);
    }

    public function importFromUrl(string $url): void
    {
        $response = Http::get($url);

        if (!$response->successful()) {
            Log::error("XML-Import fehlgeschlagen: HTTP " . $response->status());
            return;
        }

        $xml = simplexml_load_string($response->body());
        $this->process($xml);
    }

    protected function process(\SimpleXMLElement $xml): void
    {
        $products = $xml->xpath($this->productNodePath());

        foreach ($products as $product) {
            try {
                $data = $this->mapXmlToData($product);
                $data = $this->sanitize($data);
                $data = array_merge($data, $this->fixedValues());

                $this->upsertRecord($data);
            } catch (\Throwable $e) {
                Log::error("Fehler beim XML-Import: " . $e->getMessage(), ['product' => $product]);
            }
        }

        Log::info("XML-Import abgeschlossen", ['anzahl' => count($products)]);
    }

    protected function sanitize(array $data): array
    {
        return collect($data)->map(function ($value, $key) {
            return match ($key) {
                'width', 'length', 'height', 'weight' => CsvValueSanitizer::toScaledInt($value, 100),
                default => CsvValueSanitizer::toNullableString($value),
            };
        })->all();
    }

    protected function upsertRecord(array $data): void
    {
        $model = $this->model();
        $record = $model::where('productnumber', $data['productnumber'] ?? null)->first();

        if ($record) {
            $record->update($data);
            Log::info("Produkt aktualisiert (XML)", ['id' => $record->id]);
        } else {
            $model::create($data);
            Log::info("Neues Produkt erstellt (XML)", ['productnumber' => $data['productnumber']]);
        }
    }
}
