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
            $attributeId = $definition['id'];
            $label = $definition['label'];

            return Tables\Columns\TextColumn::make('attribute_' . $attributeId)
                ->label($label)
                ->state(function (ProductVariation $record) use ($attributeId): string {
                    $value = $record->attributeValues
                        ->first(function ($attributeValue) use ($attributeId) {
                            return (int) $attributeValue->attribute_id === $attributeId;
                        });

                    $displayValue = trim((string) ($value->value ?? ''));

                    return $displayValue !== '' ? $displayValue : '—';
                })
                ->wrap();
        }, $definitions);
    }

    /**
     * Ermittelt die sichtbaren Attribut-Definitionen für die Varianten-Tabelle.
     *
     * Aktuell erfolgt die Reihenfolge stabil alphabetisch nach Attributnamen,
     * da noch kein dediziertes Sortierfeld vorhanden ist.
     *
     * @return array<int, array{id:int,label:string}>
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

        $attributes = [];

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

                $attributes[$attributeId] = [
                    'id' => $attributeId,
                    'label' => $attributeName,
                ];
            }
        }

        $attributes = array_values($attributes);

        usort($attributes, function (array $left, array $right): int {
            return strcasecmp($left['label'], $right['label']);
        });

        return array_slice($attributes, 0, 5);
    }
}
