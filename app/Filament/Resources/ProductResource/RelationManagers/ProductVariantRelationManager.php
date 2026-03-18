<?php

/**
 * @file
 * RelationManager für die Anzeige von Produkt-Varianten in der Produkt-Detailansicht.
 *
 * Bindet die Beziehung `variations()` des Product-Models ein und zeigt eine Tabelle der Varianten.
 * Zusätzlich werden die Attribut-Kombinationen der Variante (z. B. Farbe/Größe) in einer Spalte
 * „Attribute“ zusammengefasst dargestellt.
 */

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductVariation;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductVariantRelationManager extends RelationManager
{
  /**
   * Name der Eloquent-Beziehung im Product-Model.
   *
   * @var string
   */
  protected static string $relationship = 'variations';

  /**
   * Statischer Titel des Relation-Managers (Tab-Titel + Tabellenüberschrift).
   *
   * @var string|null
   */
  protected static ?string $title = 'Varianten';

    /**
     * Konfiguration der Varianten-Tabelle.
     *
     * - Lädt Attributwerte + zugehörige Attribute eager.
     * - Zeigt SKU, Variantenname und bis zu 5 Attribut-Spalten.
     *
     * @param \Filament\Tables\Table $table
     * @return \Filament\Tables\Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query->with(['attributeValues.attribute']);
            })
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('Artikelnummer')
                    ->searchable(),

                ...$this->buildVariantAttributeColumns(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Keine Varianten gefunden');
    }

    /**
     * Baut bis zu 5 dynamische Attribut-Spalten für die Varianten-Tabelle.
     *
     * @return array<int, \Filament\Tables\Columns\TextColumn>
     */
    protected function buildVariantAttributeColumns(): array
    {
        $definitions = $this->getVariantAttributeDefinitions();

        return array_map(function (array $definition): Tables\Columns\TextColumn {
            return Tables\Columns\TextColumn::make('attribute_' . md5($definition['key']))
                ->label($definition['label'])
                ->state(function (ProductVariation $record) use ($definition): string {
                    if ($definition['source'] === 'relation') {
                        $attributeId = (int) str_replace('attr_', '', $definition['key']);

                        $value = $record->attributeValues
                            ->first(fn($attributeValue) => (int) $attributeValue->attribute_id === $attributeId);

                        $displayValue = trim((string) ($value->value ?? ''));

                        return $displayValue !== '' ? $displayValue : '—';
                    }

                    $json = $record->attributes_json;

                    if (! is_array($json)) {
                        return '—';
                    }

                    $displayValue = trim((string) ($json[$definition['key']] ?? ''));

                    return $displayValue !== '' ? $displayValue : '—';
                })
                ->wrap();
        }, $definitions);
    }

    /**
     * Ermittelt die sichtbaren Attribut-Definitionen für die Varianten-Tabelle.
     *
     * Verwendet bevorzugt relationale Attributwerte. Falls keine vorhanden sind,
     * wird auf attributes_json zurückgegriffen.
     *
     * @return array<int, array{key:string,label:string,source:string}>
     */
    protected function getVariantAttributeDefinitions(): array
    {
        $ownerRecord = $this->getOwnerRecord();

        if (! $ownerRecord || ! method_exists($ownerRecord, 'variations')) {
            return [];
        }

        $variations = $ownerRecord->variations()
            ->with(['attributeValues.attribute'])
            ->get();

        $hasRelationalAttributes = $variations->contains(function ($variation): bool {
            return $variation->attributeValues->isNotEmpty();
        });

        $attributes = [];

        if ($hasRelationalAttributes) {
            foreach ($variations as $variation) {
                foreach ($variation->attributeValues as $attributeValue) {
                    if (! $attributeValue->attribute) {
                        continue;
                    }

                    $attributeId = (int) $attributeValue->attribute->id;
                    $attributeName = trim((string) $attributeValue->attribute->name);

                    if ($attributeId <= 0 || $attributeName === '') {
                        continue;
                    }

                    $attributes['attr_' . $attributeId] = [
                        'key' => 'attr_' . $attributeId,
                        'label' => $attributeName,
                        'source' => 'relation',
                    ];
                }
            }
        } else {
            foreach ($variations as $variation) {
                $json = $variation->attributes_json;

                if (! is_array($json)) {
                    continue;
                }

                foreach ($json as $rawKey => $rawValue) {
                    $key = trim((string) $rawKey);

                    if ($key === '') {
                        continue;
                    }

                    $label = preg_replace('/^Attribute Group:\s*/i', '', $key) ?? $key;
                    $label = trim($label);

                    $attributes[$key] = [
                        'key' => $key,
                        'label' => $label !== '' ? $label : $key,
                        'source' => 'json',
                    ];
                }
            }
        }

        $attributes = array_values($attributes);

        usort($attributes, function (array $left, array $right): int {
            return strcasecmp($left['label'], $right['label']);
        });

        return array_slice($attributes, 0, 5);
    }

}
