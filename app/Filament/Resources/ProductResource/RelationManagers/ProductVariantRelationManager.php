<?php

/**
 * @file
 * RelationManager für die Anzeige von Produkt-Varianten in der Produkt-Detailansicht.
 *
 * Dieser Manager bindet die Beziehung `variations()` des Product-Models ein
 * und zeigt eine Tabelle der Varianten (z. B. SKU, Name, Preis) an.
 */

namespace App\Filament\Resources\ProductResource\RelationManagers;

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
   * @param \Filament\Tables\Table $table
   * @return \Filament\Tables\Table
   */
  public function table(Table $table): Table
  {
    return $table
      ->recordTitleAttribute('sku') // ggf. anpassen, falls keine SKU vorhanden
      ->columns([
        Tables\Columns\TextColumn::make('sku')
          ->label('SKU')
          ->searchable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('name')
          ->label('Variantenname')
          ->wrap()
          ->toggleable(),

        Tables\Columns\TextColumn::make('price')
          ->label('Preis')
          ->money('EUR', true)
          ->sortable()
          ->toggleable(),
      ])
      ->paginated([10, 25, 50])
      ->defaultPaginationPageOption(10)
      ->emptyStateHeading('Keine Varianten gefunden');
  }
}
