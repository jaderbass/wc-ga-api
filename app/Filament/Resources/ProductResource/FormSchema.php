<?php

namespace App\Filament\Resources\ProductResource;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Support\Str;

/**
 * FormSchema
 *
 * Ausgelagertes Formular-Schema für die ProductResource.
 * - SKU ist nur bei product_type = 'simple' relevant/erforderlich.
 * - Bei Umschalten auf 'variable' wird die SKU automatisch geleert und das Feld deaktiviert.
 * - "Password-like" Verhalten beim Editieren: SKU wird leer angezeigt, Placeholder zeigt den alten Wert,
 *   und es wird nur gespeichert, wenn ein neuer Wert eingegeben wird.
 *
 * Integration:
 * - In ProductResource::form() → schema(FormSchema::fields())
 *
 * Hinweise:
 * - DB: products.product_type = ENUM('simple','variable'); products.sku = nullable (Migration).
 * - Woo Best Practice: Parent-SKU bei 'variable' leer lassen; SKUs gehören auf Variantenebene.
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
      Section::make('Stammdaten')
        ->description('Grundlegende Produktinformationen')
        ->schema([
          Grid::make(12)->schema([
            // Produkttyp steuert die SKU-Logik
            Select::make('product_type')
              ->label('Produkttyp')
              ->options([
                'simple'   => 'Einfaches Produkt',
                'variable' => 'Variables Produkt',
              ])
              ->required()
              ->reactive()
              ->afterStateUpdated(function ($state, callable $set) {
                // Wechsel auf "variable" → Parent-SKU leeren
                if ($state === 'variable') {
                  $set('sku', null);
                }
              })
              ->columnSpan(3),
          ]), // Grid
          Grid::make(12)->schema([
            TextInput::make('product_name')
              ->label('Produktname')
              ->required()
              ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug((string)$state)))
              ->maxLength(255)
              ->columnSpan(4),

            TextInput::make('slug')
              ->label('Slug')
              ->helperText('URL-Teil, automatisch aus dem Namen. Kollisionen werden serverseitig aufgelöst.')
              ->required()
              ->columnSpan(4),

            TextInput::make('product_number')
              ->label('Produktnummer')
              ->maxLength(64)
              ->helperText('Interne/Hersteller-Artikelnummer')
              ->columnSpan(4),

            TextInput::make('ean')
              ->label('EAN')
              ->maxLength(32) // EAN-13 passt; etwas Luft für Varianten/Präfixe
              ->rule('regex:/^[0-9\- ]*$/') // nur Ziffern, Bindestrich, Leerzeichen
              ->helperText('Nur Ziffern, ggf. mit Bindestrich/Leerzeichen')
              ->columnSpan(4),

            Select::make('manufacturer_id')
              ->required()
              ->relationship('manufacturer', 'manufacturer')
              ->columnSpan(4),

            // SKU-Feld – kombiniert deine alten Komfort-Features mit der neuen Logik
            TextInput::make('sku')
              ->label('SKU (nur Parent bei simple)')
              ->nullable()
              // Beim Editieren „password-like“: leer anzeigen
              ->formatStateUsing(fn($state, $record, string $context) => $context === 'edit' ? '' : $state)
              // Alte SKU als Platzhalter, damit man sie sieht ohne sie zu übernehmen
              ->placeholder(fn($record) => $record?->sku)
              // Required NUR beim Erstellen UND nur, wenn product_type = simple
              ->required(fn(Get $get, string $context) => $get('product_type') === 'simple' && $context === 'create')
              // Unique, aber aktuellen Datensatz ignorieren
              ->unique(ignoreRecord: true)
              // Beim Editieren nur speichern, wenn etwas eingegeben wurde (leer = unverändert)
              ->dehydrated(fn($state) => filled($state))
              // Bei variable-Parent deaktivieren (macht die UI eindeutig)
              ->disabled(fn(Get $get) => $get('product_type') === 'variable')
              ->maxLength(255)
              ->helperText('Bei variablen Produkten bitte die SKU leer lassen. Beim Bearbeiten leer lassen, um die bestehende SKU zu behalten.')
              ->columnSpan(4),

          ]) //Grid
        ]) //schema
        ->collapsible(),

      // ⬇️ Hier deine weiteren Sections/Tabs/Grids ergänzen …
      Section::make('Beschreibungen')
          ->description('Weiterführende Produktinformationen')
          ->schema([
            Grid::make(12)->schema([
              Textarea::make('description')
                ->label('Beschreibung')
                ->rows(6)
                ->required()
                ->maxLength(65535)
                ->columnSpan(6),

              Textarea::make('short_description')
                ->label('Kurzbeschreibung')
                ->rows(6)
                ->required()
                ->maxLength(65535)
                ->columnSpan(6),

            ]) // Grid
          ]) //schema
          ->collapsible(),

        Section::make('Maße')
          ->description('Produkt- und Verpackungsmaße')
          ->schema([
            Grid::make(12)->schema([
              Checkbox::make('unit')
                ->label('Unit')
                ->columnSpanFull(),
              TextInput::make('width')
                ->helperText('Width in mm')
                ->columnSpan(3),
              TextInput::make('length')
                ->helperText('Length in mm')
                ->columnSpan(3),
              TextInput::make('height')
                ->helperText('Height in mm')
                ->columnSpan(3),
              TextInput::make('weight')
                ->helperText('Weight in g')
                ->columnSpan(3),
              TextInput::make('box_width')
                ->helperText('Box width in mm')
                ->columnSpan(3)
                ->hidden(fn(Get $get): bool => $get('unit')),
              TextInput::make('box_length')
                ->helperText('Box length in mm')
                ->columnSpan(3)
                ->hidden(fn(Get $get): bool => $get('unit')),
              TextInput::make('box_height')
                ->helperText('Box height in mm')
                ->columnSpan(3)
                ->hidden(fn(Get $get): bool => $get('unit')),
              TextInput::make('size')
                ->label('Größe (frei)')
                ->maxLength(128)
                ->columnSpan(3),
            ]) // Grid
          ]) // schema
          ->collapsible(),

        Section::make('Unterlagen')
          ->description('Gebrauchsanweisung/Zertifizierung/Konformitätserklärung')
          ->schema([
            Grid::make(12)->schema([

              TextInput::make('external_url')
                ->label('Externe URL')
                ->url()
                ->maxLength(2048)
                ->columnSpan(3),

              TextInput::make('declaration_of_compliance')
                ->label('Konformitätserklärung')
                ->maxLength(512)
                ->columnSpan(3),

              TextInput::make('manual_url')
                ->label('Manual / Handbuch')
                ->url()
                ->maxLength(2048)
                ->columnSpan(3),


              TextInput::make('certification')
                ->label('Zertifizierung')
                ->maxLength(255)
                ->columnSpan(3),
            ]) // Grid
          ]) //schema
          ->collapsible(),

        Section::make('Author')
    ];
  }
}
