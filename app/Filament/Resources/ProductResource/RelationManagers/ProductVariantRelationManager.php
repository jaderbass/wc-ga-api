<?php

/**
 * @file
 * RelationManager für die Anzeige von Produkt-Varianten in der Produkt-Detailansicht.
 *
 * Zeigt je Variante u. a. SKU, EAN, Preis sowie die Attribut-Kombinationen (z. B. "Farbe: Blau · Größe: L").
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
   * Titel der Relation (Tab-Überschrift).
   *
   * @var string|null
   */
  protected static ?string $title = 'Varianten';

  /**
   * Konfiguration der Varianten-Tabelle.
   *
   * - Lädt Attributwerte + Attribute eager (verhindert N+1-Queries).
   * - Zeigt eine zusammengefasste Attribut-Spalte.
   *
   * @param \Filament\Tables\Table $table
   * @return \Filament\Tables\Table
   */
  public function table(Table $table): Table
  {
    return $table
      // Eager Loading: Attribute + deren Definition
      ->modifyQueryUsing(function ($query) {
        $query->with(['attributeValues.attribute']);
      })

      ->recordTitleAttribute('sku')

      ->columns([
        Tables\Columns\TextColumn::make('sku')
          ->label('SKU')
          ->searchable()
          ->copyable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('ean')
          ->label('EAN')
          ->searchable()
          ->copyable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('price')
          ->label('Preis')
          ->money('EUR', true)
          ->sortable()
          ->toggleable(),

        /**
         * Zusammenfassung der Attribut-Kombinationen, z. B. "Farbe: Blau · Größe: L"
         * Beruht auf der Beziehung $variation->attributeValues (BelongsToMany) und
         * $value->attribute (ProductAttribute) mit 'name' sowie $value->value.
         */
        Tables\Columns\TextColumn::make('attributes_summary')
          ->label('Attribute')
          ->state(function (ProductVariation $record): string {
            $pairs = $record->attributeValues
              ->filter(fn($v) => $v && $v->attribute)
              ->map(function ($value) {
                $attr = trim((string) $value->attribute->name);
                $val  = trim((string) $value->value);
                if ($attr === '') {
                  return $val;
                }
                if ($val === '') {
                  return $attr;
                }
                return "{$attr}: {$val}";
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
