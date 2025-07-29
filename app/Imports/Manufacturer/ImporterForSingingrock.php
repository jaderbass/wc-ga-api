<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseXmlImporter;
use App\Helpers\XmlValueSanitizer;

class ImporterForSingingRock extends BaseXmlImporter
{
    /**
     * Mapping der Produktfelder
     */
    protected function mapFields(array $row): array
    {
        return [
            'productnumber'       => XmlValueSanitizer::toNullableString($row['ARTICLE'] ?? null),
            'name'                => XmlValueSanitizer::cleanAndDecodeHtml($row['NAME'] ?? null),
            'description'         => XmlValueSanitizer::cleanAndDecodeHtml($row['DESCRIPTION'] ?? null),
            'short_description'   => XmlValueSanitizer::cleanAndDecodeHtml($row['SHORT_DESCRIPTION'] ?? null),
            'eancode'             => XmlValueSanitizer::toNullableString($row['EAN'] ?? null),
            'skucode'             => XmlValueSanitizer::toNullableString($row['SKU'] ?? null),
            'regular_price'       => XmlValueSanitizer::toNullableString($row['PRICE'] ?? null),
            'sale_price'          => XmlValueSanitizer::toNullableString($row['SALE_PRICE'] ?? null),
            'stock_quantity'      => XmlValueSanitizer::toNullableInt($row['STOCK'] ?? 0),
            'unit'                => XmlValueSanitizer::toNullableString($row['UNIT'] ?? null),
            'product_type'        => 'simple', // Standard, ggf. erweitern
            'manufacturer_id'     => 4, // ID für SingingRock in DB
        ];
    }

    /**
     * Extrahiert Variationen (falls vorhanden)
     */
    protected function parseVariations(array $row): array
    {
        if (empty($row['VARIANTS'])) {
            return [];
        }

        return collect($row['VARIANTS'])->map(function ($v) {
            return [
                'sku'             => XmlValueSanitizer::toNullableString($v['SKU'] ?? null),
                'regular_price'   => XmlValueSanitizer::toNullableString($v['PRICE'] ?? null),
                'sale_price'      => XmlValueSanitizer::toNullableString($v['SALE_PRICE'] ?? null),
                'stock_quantity'  => XmlValueSanitizer::toNullableInt($v['STOCK'] ?? 0),
                'attributes'      => json_encode($v['ATTRIBUTES'] ?? []),
            ];
        })->toArray();
    }

    /**
     * Extrahiert Bilder
     */
    protected function parseImages(array $row): array
    {
        if (empty($row['IMAGES'])) {
            return [];
        }

        return collect($row['IMAGES'])->map(fn($url) => [
            'url'     => (string) $url,
            'is_main' => false,
        ])->toArray();
    }

    /**
     * Parsen des XML in Array-Struktur für handle()
     */
    protected function parseXml(\SimpleXMLElement $xml): array
    {
        $products = [];

        foreach ($xml->PRODUCTS->PRODUCTITEM as $item) {
            $products[] = [
                'ARTICLE'           => (string) $item->ARTICLE,
                'NAME'              => (string) $item->NAME,
                'DESCRIPTION'       => (string) $item->DESCRIPTION,
                'SHORT_DESCRIPTION' => (string) $item->SHORT_DESCRIPTION,
                'EAN'               => (string) $item->EAN,
                'SKU'               => (string) $item->SKU,
                'PRICE'             => (string) $item->PRICE,
                'SALE_PRICE'        => (string) $item->SALE_PRICE,
                'STOCK'             => (string) $item->STOCK,
                'UNIT'              => (string) $item->UNIT,
                'IMAGES'            => collect($item->IMAGES->IMAGE ?? [])->map(fn($img) => (string) $img)->toArray(),
                'VARIANTS'          => $this->parseVariantsFromXml($item->VARIANTS ?? null),
            ];
        }

        return $products;
    }

    /**
     * Hilfsmethode: Variationen aus XML extrahieren
     */
    private function parseVariantsFromXml($variantsNode): array
    {
        if (!$variantsNode) {
            return [];
        }

        $variants = [];
        foreach ($variantsNode->VARIANT as $variant) {
            $variants[] = [
                'SKU'         => (string) $variant->SKU,
                'PRICE'       => (string) $variant->PRICE,
                'SALE_PRICE'  => (string) $variant->SALE_PRICE,
                'STOCK'       => (string) $variant->STOCK,
                'ATTRIBUTES'  => $this->parseAttributesFromXml($variant->ATTRIBUTES ?? null),
            ];
        }

        return $variants;
    }

    /**
     * Hilfsmethode: Attribute aus XML extrahieren
     */
    private function parseAttributesFromXml($attributesNode): array
    {
        if (!$attributesNode) {
            return [];
        }

        $attributes = [];
        foreach ($attributesNode->ATTRIBUTE as $attr) {
            $attributes[(string) $attr->NAME] = (string) $attr->VALUE;
        }

        return $attributes;
    }
}
