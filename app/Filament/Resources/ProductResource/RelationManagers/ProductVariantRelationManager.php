<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class ProductVariantRelationManager extends RelationManager
{
  protected static string $relationship = 'variants';

  public function table(Tables\Table $table): Tables\Table
  {
    return $table
      ->columns([
        TextColumn::make('color')->label('Farbe'),
        TextColumn::make('size')->label('Größe'),
        TextColumn::make('sku')->label('SKU'),
        TextColumn::make('price')->label('Preis'),
        TextColumn::make('stock')->label('Lagerbestand'),
      ]);
  }
}
