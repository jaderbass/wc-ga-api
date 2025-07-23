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
                'productnumber'      => XmlValueSanitizer::toNullableString($entry->CODE),
                'productname'        => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->NAME),
                'description'        => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->TEXTLONG),
                'shortdescription'   => XmlValueSanitizer::cleanAndDecodeHtml((string) $entry->TEXTSHORT),
                'eancode'            => XmlValueSanitizer::toNullableString($entry->EAN),
                'skucode'            => XmlValueSanitizer::toNullableString($entry->PARTNO),
                'price'              => XmlValueSanitizer::toIntCents($entry->PRICE),
                'regularprice'       => XmlValueSanitizer::toIntCents($entry->PRICE),
                'saleprice'          => XmlValueSanitizer::toIntCents($entry->PRICE_SALE),
                'width'              => XmlValueSanitizer::toScaledInt($entry->WIDTH, 100),
                'length'             => XmlValueSanitizer::toScaledInt($entry->LENGTH, 100),
                'height'             => XmlValueSanitizer::toScaledInt($entry->HEIGHT, 100),
                'weight'             => XmlValueSanitizer::toScaledInt($entry->WEIGHT, 100),
                'unit'               => XmlValueSanitizer::toNullableString($entry->UNIT),
                'unitprice'          => XmlValueSanitizer::toIntCents($entry->UNIT_PRICE),
                'pcsperbox'          => XmlValueSanitizer::toNullableInt($entry->PCS_PER_BOX),
                'boxwidth'           => XmlValueSanitizer::toScaledInt($entry->BOX_WIDTH, 100),
                'boxlength'          => XmlValueSanitizer::toScaledInt($entry->BOX_LENGTH, 100),
                'boxheight'          => XmlValueSanitizer::toScaledInt($entry->BOX_HEIGHT, 100),
            ];
        }

        Log::debug('→ Produktdaten nach Mapping:', $products);
        
        return $products ?? [];
    }
}
