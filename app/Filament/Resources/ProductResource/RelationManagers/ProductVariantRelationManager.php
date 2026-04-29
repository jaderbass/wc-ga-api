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
use App\Services\ProductNaming\VariationDisplayNameResolver;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

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

                Tables\Columns\TextColumn::make('variant_name')
                    ->label('Variantenname')
                    ->state(function (ProductVariation $record): string {
                        return app(VariationDisplayNameResolver::class)->resolve($record);
                    })
                    ->wrap(),


                ...$this->buildVariantAttributeColumns(),

                Tables\Columns\TextColumn::make('manufacturer_price_cents')
                    ->label('Herstellerpreis')
                    ->state(fn($record) => $record->manufacturer_price_cents ?? 'TEST-NULL')
                    ->formatStateUsing(fn($state) => filled($state) && (int) $state > 0
                        ? number_format(((int) $state) / 100, 2, ',', '.') . ' €'
                        : '—')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Schnell bearbeiten')
                    ->icon('heroicon-m-pencil-square')
                    ->modalHeading('Variante schnell bearbeiten')
                    ->modalSubmitActionLabel('Speichern')
                    ->modalCancelActionLabel('Abbrechen')
                    ->form([
                        Forms\Components\TextInput::make('sku')
                            ->label('Artikelnummer')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('manufacturer_price_cents')
                            ->label('Herstellerpreis')
                            ->suffix('€')
                            ->formatStateUsing(
                                fn($state) =>
                                filled($state)
                                    ? number_format(((int) $state) / 100, 2, ',', '')
                                    : null
                            )
                            ->dehydrateStateUsing(
                                fn($state) =>
                                filled($state)
                                    ? (int) round((float) str_replace(',', '.', $state) * 100)
                                    : null
                            ),

                        Forms\Components\KeyValue::make('attributes_json')
                            ->label('JSON-Attribute')
                            ->keyLabel('Attribut')
                            ->valueLabel('Wert')
                            ->addActionLabel('Attribut hinzufügen')
                            ->reorderable(),
                    ]),

                Tables\Actions\Action::make('editVariation')
                    ->iconButton()
                    ->tooltip('Details bearbeiten')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->url(
                        fn(ProductVariation $record) =>
                        \App\Filament\Resources\ProductVariationResource::getUrl('edit', [
                            'record' => $record,
                        ])
                    ),
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

    /**
     * Aktualisiert die Varianten-Tabelle nach einer Neugenerierung des Produktnamens.
     *
     * Der Variantenname wird in der Tabelle dynamisch über den
     * VariationDisplayNameResolver berechnet. Nach einer Action auf der
     * Edit-Seite muss der Relation Manager daher neu gerendert werden,
     * damit die aktualisierten Attributwerte sofort sichtbar sind.
     *
     * @return void
     */
    #[On('refresh-product-variations')]
    public function refreshProductVariations(): void
    {
        // Leerer Listener genügt hier, damit Livewire den Component-Render
        // erneut durchläuft und die Tabellen-States neu berechnet.
    }
}
