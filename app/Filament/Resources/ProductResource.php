<?php

/**
 * @file
 * ProductResource – Formular um Produkt-Stammdaten (inkl. Name, Produktnummer, EAN) korrekt in der Detailansicht (Edit) anzuzeigen.
 *
 * Diese Resource zeigt im Edit-Formular explizit die Felder:
 * - name
 * - product_number
 * - ean
 *
 * Weitere Felder/Abschnitte können unverändert bestehen bleiben.
 */

namespace App\Filament\Resources;

use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Manufacturer;
use App\Models\Product;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
// use App\Filament\Resources\ImporterSelector;
use App\Services\ImporterSelector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Placeholder;

class ProductResource extends Resource
{
  /**
   * Zugehöriges Eloquent-Model.
   *
   * @var class-string<\App\Models\Product>
   */
  protected static ?string $model = Product::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  /**
   * Formularschema für Create/Edit.
   *
   * Ergänzt explizit die Felder `name`, `product_number` und `ean`,
   * damit diese in der Detailansicht sichtbar und bearbeitbar sind.
   *
   * @param \Filament\Forms\Form $form
   * @return \Filament\Forms\Form
   */
  public static function form(Form $form): Form
  {
    return $form
      ->schema([
      Forms\Components\Section::make('Stammdaten')
        ->description('Grundlegende Produktinformationen')
        ->schema([
          Forms\Components\Grid::make(12)->schema([
            Forms\Components\TextInput::make('product_name')
              ->label('Produktname')
              ->required()
              ->maxLength(255)
              ->columnSpan(8),

            Forms\Components\TextInput::make('product_number')
              ->label('Produktnummer')
              ->maxLength(64)
              ->helperText('Interne/Hersteller-Artikelnummer')
              ->columnSpan(4),

            Forms\Components\TextInput::make('ean')
              ->label('EAN')
              ->maxLength(32) // EAN-13 passt; etwas Luft für Varianten/Präfixe
              ->rule('regex:/^[0-9\- ]*$/') // nur Ziffern, Bindestrich, Leerzeichen
              ->helperText('Nur Ziffern, ggf. mit Bindestrich/Leerzeichen')
              ->columnSpan(4),
          ]),
        ])
        ->collapsible(),
        Forms\Components\Select::make('manufacturer_id')
          ->required()
          ->relationship('manufacturer', 'manufacturer')
          // ->columnSpanFull(),
          ->columnSpan(9),
        /* Forms\Components\TextInput::make('product_number')
          ->maxLength(100)
          ->columnSpan(3), */
        /* Forms\Components\TextInput::make('ean')
          ->maxLength(14)
          ->columnSpan(3), */
        Forms\Components\TextInput::make('sku')
          ->maxLength(32)
          ->columnSpan(3),
        /* Forms\Components\TextInput::make('product_name')
          ->required()
          ->maxLength(100)
          ->columnSpan(3), */
        Forms\Components\Textarea::make('description')
          ->required()
          ->columnSpan(6),
        Forms\Components\TextInput::make('shortdescription')
          ->columnSpan(6),
        Forms\Components\TextInput::make('price')
          ->required()
          ->numeric()
          ->integer()
          ->columnSpan(2),
        Forms\Components\TextInput::make('regular_price')
          ->required()
          ->numeric()
          ->integer()
          ->columnSpan(2),
        Forms\Components\TextInput::make('sale_price')
          ->required()
          ->numeric()
          ->integer()
          ->columnSpan(2),
        Forms\Components\TextInput::make('width')
          ->numeric()
          ->integer()
          ->helperText('Width in mm')
          ->columnSpan(2),
        Forms\Components\TextInput::make('length')
          ->numeric()
          ->integer()
          ->helperText('Length in mm')
          ->columnSpan(2),
        Forms\Components\TextInput::make('height')
          ->numeric()
          ->integer()
          ->helperText('Height in mm')
          ->columnSpan(2),
        Forms\Components\TextInput::make('weight')
          ->numeric()
          ->integer()
          ->helperText('Weight in mm')
          ->columnSpan(2),
        Forms\Components\Checkbox::make('unit')
          ->label('Unit')
          ->columnSpanFull(),
        Forms\Components\TextInput::make('unit_price')
          ->numeric()
          ->integer()
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('pcs_per_box')
          ->numeric()
          ->integer()
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('box_width')
          ->numeric()
          ->integer()
          ->helperText('Box width in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('box_length')
          ->numeric()
          ->integer()
          ->helperText('Box length in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('box_height')
          ->numeric()
          ->integer()
          ->helperText('Box height in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
      ])
      ->columns(12);
  }

  /**
   * Tabellen-Konfiguration (unverändert – nur Platzhalter,
   * belasse hier deinen bestehenden Inhalt).
   *
   * @param \Filament\Tables\Table $table
   * @return \Filament\Tables\Table
   */
  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        Tables\Columns\TextColumn::make('product_name')
          ->label('Produktname')
          ->formatStateUsing(fn($state) => $state ? \Illuminate\Support\Str::limit((string)$state, 20) : '—')
          ->tooltip(fn($state) => $state ?: null)
          ->searchable()
          ->sortable()
          ->wrap()
          ->toggleable(),

        Tables\Columns\TextColumn::make('manufacturer.manufacturer')
          ->label('Hersteller')
          ->searchable()
          ->sortable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('product_number')
          ->label('Artikelnummer')
          ->searchable()
          ->sortable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('ean')
          ->label('EAN')
          ->searchable()
          ->sortable()
          ->toggleable(),

        // NEU: hart auf 45 Zeichen begrenzen + Tooltip mit vollem Text
        Tables\Columns\TextColumn::make('short_description')
          ->label('Kurzbeschreibung')
          // 1) State aus Record ableiten: short_description ODER Fallback auf description
          ->state(fn($record) => $record->short_description ?: $record->description)
          // 2) Anzeige kürzen
          ->formatStateUsing(fn($state) => $state ? \Illuminate\Support\Str::limit((string) $state, 40) : '—')
          // 3) Tooltip: voller Text (gleicher Fallback)
          ->tooltip(fn($record) => ($record->short_description ?: $record->description) ?: null)
          ->toggleable(),
      ])
      ->defaultSort('product_name')
      ->paginated([10, 25, 50])
      ->defaultPaginationPageOption(25)
      ->filters([
        Tables\Filters\SelectFilter::make('manufacturer_id')
          ->label('Hersteller')
          ->relationship('manufacturer', 'manufacturer') // Relation + anzuzeigendes Feld
          ->multiple()                                   // ⬅️ Mehrfachauswahl aktivieren
          ->searchable()
          ->preload()
          ->indicator('Hersteller'),                     // hübscher Filter-Badge-Text
    ])
      ->actions([
        Tables\Actions\EditAction::make(),
      ])
      ->headerActions([
        Tables\Actions\Action::make('importProducts')
          ->label('Import starten')
          ->form([
            Forms\Components\Select::make('manufacturer_id')
                ->label('Hersteller')
                ->relationship('manufacturer', 'manufacturer')
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                  // Automatisch den Import-Typ setzen
                  $importType = \App\Models\Manufacturer::find($state)?->import_type ?? 'csv';
                  $set('sourceType', $importType);
                })
                ->required(),
            Forms\Components\Hidden::make('sourceType')
              ->default(fn($get) => \App\Models\Manufacturer::find($get('manufacturer_id'))?->import_type ?? 'csv'),
                // Info-Box bei API-Import
                Placeholder::make('api_info')
                  ->label('')
                  ->content(
                    fn($get) =>
                    $get('sourceType') === 'api'
                      ? 'Die Daten werden automatisch über die API dieses Herstellers abgerufen. Kein Datei-Upload erforderlich.'
                      : ''
                  )
              ->visible(fn($get) => $get('sourceType') === 'api'),

            Forms\Components\FileUpload::make('csv')
              ->label('CSV-Datei')
              ->acceptedFileTypes(['text/csv'])
              ->visible(fn($get) => $get('sourceType') === 'csv')
              ->storeFiles(false),

            Forms\Components\FileUpload::make('xml')
              ->label('XML-Datei')
              ->acceptedFileTypes(['text/xml', 'application/xml'])
              ->visible(fn($get) => $get('sourceType') === 'xml')
              ->storeFiles(false),
          ])
          ->action(function (array $data) {
            if (in_array($data['sourceType'], ['csv', 'xml']) && empty($data[$data['sourceType']])) {
              Notification::make()
                ->title('Bitte wählen Sie eine Datei für den Import aus.')
                ->danger()
                ->send();
              return;
            }

            $source = match ($data['sourceType']) {
              'csv', 'xml' => Storage::disk('local')->putFile('imports', $data[$data['sourceType']]),
              'api'        => $data['api_url'],
            };

            Log::info('Import gestartet', [
              'manufacturer_id' => $data['manufacturer_id'],
              'sourceType' => $data['sourceType'],
              'source' => $source,
            ]);

            try {
              if ($data['sourceType'] === 'csv') {
                // Mapping bestimmen (Fallback 'petzl')
                $mapping = \App\Models\Manufacturer::find($data['manufacturer_id'])?->slug ?? 'petzl';
                $fullPath = storage_path("app/{$source}");

              // CSV → unser Varianten-Importer (legt products + product_variations an)
              (new \App\Importers\GenericCsvProductImporter(
                mappingFile: $mapping,
                manufacturerId: (int) $data['manufacturer_id'] // 👈 neu
              ))->import($fullPath);

                Notification::make()->title('CSV-Import abgeschlossen')->success()->send();
                return;
              }

              // Für XML/API bleibt deine bisherige Pipeline aktiv
              $importer = \App\Services\ImporterSelector::forManufacturer($data['manufacturer_id']);
              \App\Services\ImporterSelector::handleImport($importer, $data['sourceType'], $source);

              Notification::make()->title('Import gestartet')->success()->send();
            } catch (\Throwable $e) {
              Log::error('Import fehlgeschlagen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
              ]);
              Notification::make()
                ->title('Import fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
            }
          }),

      ])
      ->bulkActions([
        Tables\Actions\BulkActionGroup::make([
          Tables\Actions\DeleteBulkAction::make(),
        ]),
      ]);
  }

  public static function getRelations(): array
  {
    return [
      \App\Filament\Resources\ProductResource\RelationManagers\ProductVariantRelationManager::class,
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListProducts::route('/'),
      'create' => Pages\CreateProduct::route('/create'),
      'edit' => Pages\EditProduct::route('/{record}/edit'),
    ];
  }
}
