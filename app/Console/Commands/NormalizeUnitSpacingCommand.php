<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductNameNormalizer;
use Illuminate\Console\Command;

class NormalizeUnitSpacingCommand extends Command
{
    protected $signature = 'products:normalize-unit-spacing {--dry-run : Nur Änderungen anzeigen}';

    protected $description = 'Normalisiert Leerzeichen zwischen Zahlen und Einheiten in Alt-Daten.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $changedProducts = 0;
        $changedVariations = 0;
        $changedAttributeValues = 0;
        $changedJsonValues = 0;

        Product::with(['variations.attributeValues.attribute'])->chunkById(
            100,
            function ($products) use (
                $dryRun,
                &$changedProducts,
                &$changedVariations,
                &$changedAttributeValues,
                &$changedJsonValues
            ): void {
                foreach ($products as $product) {
                    $productChanged = false;

                    foreach ($product->variations as $variation) {
                        $variationChanged = false;

                        foreach ($variation->attributeValues as $attributeValue) {
                            $oldValue = $attributeValue->value;
                            $newValue = ProductNameNormalizer::normalizeAttributeValue($oldValue);

                            if ($oldValue !== $newValue) {
                                $changedAttributeValues++;
                                $variationChanged = true;
                                $productChanged = true;

                                $this->line(
                                    "Variation #{$variation->id} AttributeValue #{$attributeValue->id}: '{$oldValue}' -> '{$newValue}'"
                                );

                                if (! $dryRun) {
                                    $attributeValue->value = $newValue;
                                    $attributeValue->save();
                                }
                            }
                        }

                        $attributesJson = $variation->attributes_json ?? [];

                        if (is_array($attributesJson) && $attributesJson !== []) {
                            $normalizedJson = $this->normalizeAttributesJson($attributesJson, $changedJsonValues);

                            if ($attributesJson !== $normalizedJson) {
                                $variationChanged = true;
                                $productChanged = true;

                                $this->line("Variation #{$variation->id} attributes_json wurde normalisiert.");

                                if (! $dryRun) {
                                    $variation->attributes_json = $normalizedJson;
                                }
                            }
                        }

                        if ($variationChanged) {
                            $changedVariations++;

                            if (! $dryRun) {
                                $variation->save();
                            }
                        }
                    }

                    if ($productChanged) {
                        $changedProducts++;
                    }
                }
            }
        );

        $this->newLine();
        $this->info("Geänderte Produkte: {$changedProducts}");
        $this->info("Geänderte Variationen: {$changedVariations}");
        $this->info("Geänderte Attributwerte: {$changedAttributeValues}");
        $this->info("Geänderte JSON-Werte: {$changedJsonValues}");

        return self::SUCCESS;
    }

    /**
     * Normalisiert rekursiv stringbasierte Werte in attributes_json.
     *
     * @param array<mixed> $attributesJson
     * @param int $changedJsonValues
     * @return array<mixed>
     */
    private function normalizeAttributesJson(array $attributesJson, int &$changedJsonValues): array
    {
        foreach ($attributesJson as $key => $value) {
            if (is_string($value)) {
                $normalized = ProductNameNormalizer::normalizeAttributeValue($value);

                if ($normalized !== $value) {
                    $attributesJson[$key] = $normalized;
                    $changedJsonValues++;
                }

                continue;
            }

            if (is_array($value)) {
                $attributesJson[$key] = $this->normalizeAttributesJson($value, $changedJsonValues);
            }
        }

        return $attributesJson;
    }
}
