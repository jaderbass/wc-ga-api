<?php

namespace App\Imports\Manufacturer;

use App\Models\Product;
use App\Imports\BaseXmlImporter;
use App\Helpers\XmlValueSanitizer;
use Illuminate\Support\Facades\Log;

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
}
