<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use App\Imports\BaseXmlImporter;
use App\Helpers\XmlValueSanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ImporterForSingingRock extends BaseXmlImporter
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function fixedValues(): array
    {
        return [
            'manufacturer_id' => 5, // ggf. anpassen!
        ];
    }

    protected function parseXml(\SimpleXMLElement $xml): array
    {

        if (!isset($xml->PRODUCTS->PRODUCTITEM)) {
            Log::warning('Keine Produkte im XML gefunden');
            return [];
        }

        $products = [];

        foreach ($xml->PRODUCTS->PRODUCTITEM as $entry) {
            $products[] = [
                'manufacturer_id'    => 5,
                'productnumber'      => XmlValueSanitizer::toNullableString($entry->ARTICLE),
                'productname'        => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->ARTICLE_NAME),
                'description'        => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->DESCRIPTION),
                'shortdescription'   => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->SHORT_DESCRIPTION),
                'eancode'            => XmlValueSanitizer::toNullableString($entry->EAN),
                'width'              => XmlValueSanitizer::toNullableString($entry->WIDTH, 100),
                'length'             => XmlValueSanitizer::toNullableString($entry->LENGTH, 100),
                'weight'             => XmlValueSanitizer::toNullableString($entry->WEIGHT, 100),
                'unit'               => XmlValueSanitizer::toNullableString($entry->UNIT),
            ];
        }

        Log::debug('→ Produktdaten nach Mapping:', $products);
        
        return $products ?? [];
    }
    /**
     * Handle the XML file from a URL, with basic auth.
     *
     * @param string|null $url
     */
    // This method is used to fetch the XML from the SingingRock API.
    // It uses basic authentication with credentials from the config.
    // The XML is then parsed and imported using the handleUploadedXmlFile method.
    public function handleFromUrl(string $url, ?string $user = null, ?string $password = null): void
    {
        $request = Http::when($user && $password, fn($http) => $http->withBasicAuth($user, $password));
        $response = $request->get($url);

        if (! $response->ok()) {
            Log::error("Fehler beim Abrufen der XML-URL: {$url}", ['status' => $response->status()]);
            return;
        }

        $tmpPath = storage_path('app/imports/feed-' . uniqid() . '.xml');
        file_put_contents($tmpPath, $response->body());

        $relativePath = str_replace(storage_path('app/'), '', $tmpPath);
        $this->handleFromPath($relativePath);
    }
}
