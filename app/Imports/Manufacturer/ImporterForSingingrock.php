<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseXmlImporter;
use App\Models\Product;
use App\Helpers\XmlValueSanitizer;

class ImporterForSingingRock extends BaseXmlImporter
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function fixedValues(): array
    {
        return [
            'manufacturer_id' => 5,
        ];
    }

    protected function mapXmlItem(\SimpleXMLElement $item): array
    {
        return [
            'productnumber'   => (string) $item->Code,
            'productname'     => (string) $item->ProductName,
            'description'     => (string) $item->Description,
            'eancode'         => (string) $item->EAN,
            'price'           => XmlValueSanitizer::toIntCents($item->Price),
            'width'           => XmlValueSanitizer::toScaledInt($item->Width, 10),
            'height'          => XmlValueSanitizer::toScaledInt($item->Height, 10),
            'weight'          => XmlValueSanitizer::toScaledInt($item->Weight, 1000),
            'active'          => ((string) $item->Status) === 'active',
            'variant_group'   => (string) $item->VariantGroup,
        ];
    }
}
