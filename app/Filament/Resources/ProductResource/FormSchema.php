<?php

namespace App\Filament\Resources\ProductResource;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

/**
 * FormSchema
 *
 * Ausgelagertes Formular-Schema für die ProductResource.
 * - Validiert, dass SKU nur bei product_type = 'simple' erforderlich ist.
 * - Leert SKU automatisch, wenn product_type auf 'variable' umgestellt wird.
 * - Kompatibel mit Filament 3.2.
 *
 * Integration:
 * - In ProductResource::form() -> schema(FormSchema::fields())
 * - Siehe Folge-Datei/Schritt für die minimale Einbindung.
 *
 * Hinweise:
 * - 'product_type' wird als ENUM ('simple'|'variable') in der DB geführt.
 * - 'sku' ist dank Migration NULL-fähig; bei 'variable' soll Parent-SKU leer bleiben.
 *
 * @author  JAderBass
 * @since   2025-09-23
 */
class FormSchema
{
  /**
   * Gibt das Formular-Schema zurück.
   *
   * @return array<int, \Filament\Forms\Components\Component>
   */
  public static function fields(): array
  {
    return [
      Grid::make(12)->schema([
        Select::make('product_type')
          ->label('Produkttyp')
          ->options([
            'simple'   => 'Einfaches Produkt',
            'variable' => 'Variables Produkt',
          ])
          ->required()
          ->reactive()
          ->afterStateUpdated(function ($state, callable $set) {
            // Wenn auf "variable" umgestellt wird, Parent-SKU leeren
            if ($state === 'variable') {
              $set('sku', null);
            }
          })
          ->columnSpan(3),

        TextInput::make('sku')
          ->label('SKU (nur Parent bei simple)')
          ->nullable()
          // Regel: required nur, wenn product_type === simple
          ->rule(fn(Get $get) => $get('product_type') === 'simple' ? 'required' : 'nullable')
          ->helperText('Bei variablen Produkten bitte die SKU leer lassen (SKUs gehören auf Variantenebene).')
          ->columnSpan(3),

        TextInput::make('product_name')
          ->label('Produktname')
          ->required()
          ->maxLength(255)
          ->columnSpan(6),

        TextInput::make('slug')
          ->label('Slug')
          ->maxLength(255)
          ->columnSpan(6),
      ]),

      // Weitere Felder hier ergänzen (Beschreibung, Bilder etc.) …
    ];
  }
}
