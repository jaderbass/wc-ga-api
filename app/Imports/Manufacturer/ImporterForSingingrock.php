<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseXmlImporter;
use App\Models\Product;

class ImporterForKask extends BaseXmlImporter
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function mapXmlItem(\SimpleXMLElement $item): array
    {
        return [
            'productnumber'       => (string) $item->Code,
            'productname'         => (string) $item->ProductName,
            'description'         => (string) $item->Description,
            'eancode'             => (string) $item->EAN,
            'price'               => \App\Helpers\XmlValueSanitizer::toIntCents($item->Price),
            'width'               => \App\Helpers\XmlValueSanitizer::toScaledInt($item->Width, 10),
            'height'              => \App\Helpers\XmlValueSanitizer::toScaledInt($item->Height, 10),
            'weight'              => \App\Helpers\XmlValueSanitizer::toScaledInt($item->Weight, 1000),
            'active'              => ((string) $item->Status) === 'active',
            'variant_group'       => (string) $item->VariantGroup,
            'manufacturer_id'     => 5, // Beispiel-ID für Singing Rock
        ];
    }
}
