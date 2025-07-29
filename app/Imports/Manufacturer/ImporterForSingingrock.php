<?php

namespace App\Imports\Manufacturer;

use App\Imports\BaseXmlImporter;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class ImporterForSingingRock extends BaseXmlImporter
{
    /**
     * Model für diesen Import.
     */
    protected function model(): string
    {
        return Product::class;
    }

    /**
     * Feste Werte (z. B. Hersteller-ID).
     */
    protected function fixedValues(): array
    {
        return [
            'manufacturer_id' => 2, // ID von Singing Rock in deiner DB
        ];
    }

    /**
     * Mapping der XML-Daten → Array für DB.
     */
    protected function parseXmlItem(SimpleXMLElement $entry): array
    {
        return [
            'productnumber'      => (string) $entry->ARTICLE ?? null,
            'productname'        => $this->cleanText((string) $entry->NAME),
            'description'        => $this->cleanText((string) $entry->DESCRIPTION),
            'shortdescription'   => $this->cleanText((string) $entry->SHORT_DESCRIPTION),
            'eancode'            => (string) $entry->EAN ?? null,
            'skucode'            => (string) $entry->SKU ?? null,
            'price'              => (string) $entry->PRICE ?? null,
            'regularprice'       => (string) $entry->PRICE ?? null,
            'saleprice'          => (string) $entry->PRICE_SALE ?? null,
            'width'              => (string) $entry->WIDTH ?? null,
            'length'             => (string) $entry->LENGTH ?? null,
            'height'             => (string) $entry->HEIGHT ?? null,
            'weight'             => (string) $entry->WEIGHT ?? null,
            'unit'               => (string) $entry->UNIT ?? null,
            'unitprice'          => (string) $entry->UNIT_PRICE ?? null,
            'pcsperbox'          => (string) $entry->PCS_PER_BOX ?? null,
            'boxwidth'           => (string) $entry->BOX_WIDTH ?? null,
            'boxlength'          => (string) $entry->BOX_LENGTH ?? null,
            'boxheight'          => (string) $entry->BOX_HEIGHT ?? null,
            'manufacturercountry' => (string) $entry->COUNTRY_OF_ORIGIN ?? null,
        ];
    }

    /**
     * Update oder erstelle einen Datensatz.
     */
    protected function upsertRecord(array $data): void
    {
        $model = $this->model();

        if (empty($data['productnumber'])) {
            Log::warning('❗ Kein productnumber gesetzt – Datensatz wird ignoriert', $data);
            return;
        }

        $record = $model::where('productnumber', $data['productnumber'])->first();

        if ($record) {
            $record->update($data);
            Log::info("Produkt aktualisiert", ['id' => $record->id]);
        } else {
            $model::create($data);
            Log::info("Neues Produkt erstellt", ['productnumber' => $data['productnumber']]);
        }
    }

    /**
     * Entfernt HTML und trimmt Texte.
     */
    private function cleanText(?string $text): ?string
    {
        if (!$text) {
            return null;
        }
        return strip_tags(html_entity_decode(trim($text)));
    }
}
