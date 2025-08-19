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
   * - Lädt Attributwerte + zugehörige Attribute eager (verhindert N+1).
   * - Zeigt SKU, Name, Preis und eine zusammengefasste Attribut-Ansicht.
   *
   * @param \Filament\Tables\Table $table
   * @return \Filament\Tables\Table
   */
  public function table(Table $table): Table
  {
    return $table
      // N+1 vermeiden: Attributwerte samt Attribut laden
      ->modifyQueryUsing(function ($query) {
        $query->with(['attributeValues.attribute']);
      })

      ->recordTitleAttribute('sku') // ggf. anpassen

      ->columns([
        Tables\Columns\TextColumn::make('sku')
          ->label('SKU')
          ->searchable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('name')
          ->label('Variantenname')
          ->wrap()
          ->searchable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('price')
          ->label('Preis')
          ->money('EUR', true)
          ->sortable()
          ->toggleable(),

        // Zusammenfassung der Attribut-Kombinationen, z. B. "Farbe: Blau · Größe: L"
        Tables\Columns\TextColumn::make('attributes_summary')
          ->label('Attribute')
          ->state(function (ProductVariation $record): string {
            // attributeValues ist eine BelongsToMany zu ProductAttributeValue,
            // jedes ProductAttributeValue ->attribute (ProductAttribute) mit name.
            $pairs = $record->attributeValues
              ->filter(fn($v) => $v && $v->attribute) // Safety
              ->map(function ($value) {
                $attrName  = trim((string) $value->attribute->name);
                $valueName = trim((string) $value->value);
                if ($attrName === '') {
                  return $valueName;
                }
                if ($valueName === '') {
                  return $attrName;
                }
                return "{$attrName}: {$valueName}";
              })
              ->values()
              ->all();

            return empty($pairs) ? '—' : implode(' · ', $pairs);
          })
          ->wrap()
          ->toggleable(),
      ])

      ->paginated([10, 25, 50])
      ->defaultPaginationPageOption(10)
      ->emptyStateHeading('Keine Varianten gefunden');
  }
}
