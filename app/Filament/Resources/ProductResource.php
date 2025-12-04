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

use App\Filament\Resources\ProductResource\Actions\SyncProductsBulkAction;
use App\Filament\Resources\ProductResource\Actions\SyncVariationsBulkAction;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\ImportLog;
use Filament\Forms\Form;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Class ProductResource
 *
 * Änderungen gemäß Excel (2025-08-25):
 *  - Neu hinzugefügt: external_url, declaration_of_compliance, manual_url, size,
 *    certification, author_firstname, author_lastname, author_name, author_mail
 *  - Entfernt: regular_price, sale_price, stock_quantity, status, woo_synced_at,
 *    price, unit_price, pcs_per_box, mpn
 *
 * Bestehende Felder und Struktur wurden NICHT verändert.
 */
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
        FormSection::make('Stammdaten')
          ->description('Grundlegende Produktinformationen')
          ->schema([
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
                ->native(false)        // Tom Select statt nativer <select>
                ->searchable()         // Typeahead-Suche aktivieren
                ->preload()            // Optionen vorladen (besseres UX im Modal)
                ->columnSpan(4),

              TextInput::make('sku')
                ->label('SKU')
                // Beim Editieren feldweise „password-like“: leer anzeigen
                ->formatStateUsing(fn($state, $record, string $context) => $context === 'edit' ? '' : $state)
                // Alte SKU als Platzhalter, damit man sie sieht ohne sie zu übernehmen
                ->placeholder(fn($record) => $record?->sku)
                // Nur beim Erstellen Pflicht
                ->required(fn(string $context) => $context === 'create')
                // Unique, aber aktuellen Datensatz ignorieren
                ->unique(ignoreRecord: true)
                // Beim Editieren nur speichern, wenn etwas eingegeben wurde (leer = unverändert)
                ->dehydrated(fn($state) => filled($state))
                ->maxLength(255)
                ->helperText('Beim Bearbeiten leer lassen, um die bestehende SKU zu behalten.')
                ->columnSpan(4),

            ]) //Grid
          ]) //schema
          ->collapsible(),

        FormSection::make('Beschreibungen')
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

        FormSection::make('Maße')
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

        FormSection::make('Unterlagen')
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

        FormSection::make('Author')
          ->description('Benutzerdaten von WooCommerce')
          ->schema([
            Grid::make(12)->schema([

              TextInput::make('author_firstname')
                ->label('Vorname')
                ->maxLength(100)
                ->columnSpan(3),

              TextInput::make('author_lastname')
                ->label('Nachname')
                ->maxLength(100)
                ->columnSpan(3),

              TextInput::make('author_name')
                ->label('Benutzername')
                ->maxLength(200)
                ->columnSpan(3),

              TextInput::make('author_mail')
                ->label('E-Mail')
                ->email()
                ->maxLength(255)
                ->columnSpan(3),
            ]) // Grid
          ]) //schema
          ->collapsible(),
        // BEGIN PATCH: Readonly-Section für Bilder + Edelrid-Maße
        FormSection::make('Produktdetails (readonly)')
      ->description('Automatisch importierte Informationen (nicht editierbar)')
      ->schema([
        Grid::make(12)->schema([

          // Bild-Galerie
          Placeholder::make('image_gallery')
    ->label('Produktbilder')
    ->content(function ($record) {

        if (! $record || empty($record->display_image_urls)) {
            return new HtmlString('<p class="text-sm text-gray-500">Keine Bilder vorhanden.</p>');
        }

        $html = '<div class="grid grid-cols-3 gap-4">';

        foreach ($record->display_image_urls as $url) {
            $urlEsc = e($url);

            $html .= <<<HTML
                <div class="space-y-1">
                    <div class="overflow-hidden rounded-md border bg-gray-900 h-40 flex items-center justify-center">
                        <img 
                            src="{$urlEsc}" 
                            class="max-h-full max-w-full object-contain hover:scale-110 transition-transform duration-300"
                        />
                    </div>
                    <div class="text-xs text-gray-500 break-all">{$urlEsc}</div>
                </div>
            HTML;
        }

        $html .= '</div>';

        return new HtmlString($html);
    })
    ->columnSpan(12)
    ->disableLabel(),

          // Abmessungen
          Placeholder::make('dimensions_readonly')
                ->label('Abmessungen')
                ->content(function ($record) {
                    if (! $record) {
                        return new HtmlString('');
                    }

                    $rows = [
                        'Rohmaß'      => $record->dimensions_raw,
                        'Länge (mm)'  => $record->dimension_length_mm,
                        'Breite (mm)' => $record->dimension_width_mm,
                        'Höhe (mm)'   => $record->dimension_height_mm,
                    ];

                    $html = '<table class="text-sm w-full rounded-md overflow-hidden border border-gray-700 bg-gray-900">';
                    foreach ($rows as $label => $val) {
                      $val = $val ?? '–';

                      $html .= "<tr>
                          <td class='border border-gray-700 px-2 py-1 font-medium bg-gray-800 text-gray-100'>
                              {$label}
                          </td>
                          <td class='border border-gray-700 px-2 py-1 text-gray-100'>
                              {$val}
                          </td>
                      </tr>";
                    }
                    $html .= '</table>';


                    return new HtmlString($html);
                })
                ->columnSpan(12)
                ->disableLabel()
                ->visible(fn ($record) =>
                    $record &&
                    ($record->dimensions_raw
                        || $record->dimension_length_mm
                        || $record->dimension_width_mm
                        || $record->dimension_height_mm)
                ),

        ]),
      ])
      ->columnSpan(12)
      ->collapsed(),

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

        Tables\Columns\TextColumn::make('size')
          ->label('Größe'),

        Tables\Columns\TextColumn::make('certification')
          ->label('Zertifizierung')
          ->toggleable(isToggledHiddenByDefault: true),

        Tables\Columns\TextColumn::make('external_url')
          ->label('Externe URL')
          ->limit(30)
          ->toggleable(isToggledHiddenByDefault: true),

        Tables\Columns\TextColumn::make('author_name')
          ->label('Autor*in')
          ->toggleable(isToggledHiddenByDefault: true),
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
            Select::make('manufacturer_id')
              ->label('Hersteller')
              ->placeholder('Bitte Hersteller wählen …')
              ->relationship('manufacturer', 'manufacturer')
              ->native(false)        // Tom Select statt nativer <select>
              ->searchable()         // Typeahead-Suche aktivieren
              ->preload()            // Optionen vorladen (besseres UX im Modal)
              ->reactive()
              ->afterStateUpdated(function ($state, callable $set) {
                // Automatisch den Import-Typ setzen
                $importType = \App\Models\Manufacturer::find($state)?->import_type ?? 'csv';
                $set('sourceType', $importType);
              })
              ->required(),
            Hidden::make('sourceType')
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

            FileUpload::make('csv')
              ->label('CSV-Datei')
              ->acceptedFileTypes([
                  'text/csv',
                  'text/plain',
                  'application/csv',
                  'application/vnd.ms-excel',
                  'text/x-csv',
              ])
              ->visible(fn($get) => $get('sourceType') === 'csv')
              ->storeFiles(false),

            FileUpload::make('xml')
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
              'sourceType'      => $data['sourceType'],
              'source'          => $source,
            ]);

            try {
              if (in_array($data['sourceType'] ?? '', ['csv', 'xml'], true) && empty($data[$data['sourceType']])) {
                Notification::make()->title('Bitte Datei wählen')->danger()->send();
                return;
              }

              $source = match ($data['sourceType']) {
                'csv', 'xml' => Storage::disk('local')->putFile('imports', $data[$data['sourceType']]),
                'api'        => $data['api_url'] ?? null,
                default      => null,
              };

              if (($data['sourceType'] ?? '') === 'csv') {
                $fullPath = is_string($source) ? storage_path("app/{$source}") : null;

                // schlanke Debug-Infos, nur wenn IMPORT_DEBUG=true
                ImportLog::debug('[PR] csv: path', [
                  'is_string' => is_string($fullPath),
                  'exists'    => is_string($fullPath) ? file_exists($fullPath) : false,
                ]);

                $importer = \App\Services\ImporterSelector::forManufacturer((int) $data['manufacturer_id']);
                ImportLog::debug('[PR] csv: importer', ['class' => get_debug_type($importer)]);

                \App\Services\ImporterSelector::handleImport($importer, 'csv', $fullPath);
                Notification::make()->title('CSV-Import abgeschlossen')->success()->send();
                return;
              }

              // XML / API
              $importer = \App\Services\ImporterSelector::forManufacturer((int) $data['manufacturer_id']);
              ImportLog::debug('[PR] xml/api: importer', ['class' => get_debug_type($importer)]);
              \App\Services\ImporterSelector::handleImport($importer, (string) $data['sourceType'], $source);

              Notification::make()->title('Import gestartet')->success()->send();
            } catch (\Throwable $e) {
              Log::error('ACTION_EXCEPTION', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
              ]);
              Notification::make()->title('Import fehlgeschlagen')->body($e->getMessage())->danger()->send();

              // Nur in der Entwicklungsphase:
              if (config('app.debug')) {
                throw $e; // zeigt dir im Filament-Iframe den Trace
              }
            }
          }),
      ])
      ->bulkActions([
        BulkActionGroup::make([
          DeleteBulkAction::make()->label('Löschen'),
          /**
           * Fügt eine Bulk-Action hinzu, um ausgewählte Produkte zu Woo zu synchronisieren.
           * - Optional: Only changed
           * - Optional: Dry-run
           * - Shop wählbar (Default-Shop vorbelegt)
           */
          // BulkAction::make('sync_to_woo')
          //   ->label('Zu Woo synchronisieren')
          //   ->icon('heroicon-o-arrow-up-on-square')
          //   ->deselectRecordsAfterCompletion()
          //   ->requiresConfirmation()
          //   ->form([
          //     Select::make('shop_id')
          //       ->label('Shop')
          //       ->options(Shop::query()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'))
          //       ->default(fn() => Shop::query()->where('is_default', true)->value('id'))
          //       ->required(),
          //     Toggle::make('only_changed')
          //       ->label('Nur geänderte senden')
          //       ->default(true),
          //     Toggle::make('dry_run')
          //       ->label('Dry-run (nur Vorschau)')
          //       ->default(false),
          //   ])
          //   ->action(function (Collection $records, array $data) {
          //     /** @var Shop $shop */
          //     $shop = Shop::findOrFail($data['shop_id']);
          //     /** @var WooProductService $svc */
          //     $svc = app(WooProductService::class);

          //     $ok = 0;
          //     $skip = 0;
          //     $fail = 0;
          //     $details = [];

          //     foreach ($records as $product) {
          //       try {
          //         $res = $svc->upsertProduct(
          //           $product,
          //           $shop,
          //           (bool)($data['dry_run'] ?? false),
          //           (bool)($data['only_changed'] ?? false),
          //         );

          //         if (($res['status'] ?? '') === 'error') {
          //           $fail++;
          //           $details[] = "✖ #{$product->id}: " . ($res['message'] ?? 'Unbekannter Fehler');
          //         } elseif (!empty($res['skipped'])) {
          //           $skip++;
          //           $details[] = "⏭ #{$product->id}: unverändert";
          //         } else {
          //           $ok++;
          //           $act = $res['action'] ?? 'update';
          //           $woo = $res['id'] ?? '?';
          //           $details[] = "✔ #{$product->id} → {$act} (Woo #{$woo})";
          //         }
          //       } catch (\Throwable $e) {
          //         $fail++;
          //         $details[] = "✖ #{$product->id}: " . $e->getMessage();
          //       }
          //     }

          //     $summary = "OK: {$ok} · Übersprungen: {$skip} · Fehler: {$fail}";
          //     Notification::make()
          //       ->title('Woo-Sync abgeschlossen')
          //       ->body($summary . "\n" . implode("\n", array_slice($details, 0, 8)) . (count($details) > 8 ? "\n…" : ''))
          //       ->success()
          //       ->send();
          //   }),
          SyncProductsBulkAction::make('sync_to_woo'),
          SyncVariationsBulkAction::make('sync_variations_to_woo'),
        ])
          ->label('Mehrfach-Operationen')
          ->icon('heroicon-o-arrow-path'),
      ]);
  }

  public static function getRelations(): array
  {
    return [
      \App\Filament\Resources\ProductResource\RelationManagers\ProductVariantRelationManager::class,
    ];
  }

  /**
   * Rückgabe der Seiten/Actions (Erstellen/Bearbeiten/Anzeigen)
   *
   * @return array<string, mixed>
   */
  public static function getPages(): array
  {
    return [
      'index' => Pages\ListProducts::route('/'),
      'create' => Pages\CreateProduct::route('/create'),
      'edit' => Pages\EditProduct::route('/{record}/edit'),
    ];
  }

  /**
   * Definiert die Infolist-Struktur für die Produkt-Detailansicht
   * innerhalb des Filament Admin Panels.
   *
   * Aufgaben der Infolist:
   *  - Darstellung aller Produkt-Basisdaten
   *  - Zusätzliche Ausgabe der Edelrid-spezifischen Dimensionen
   *  - Anzeige der Bildgalerie über display_image_urls
   *
   * Rückgabewert:
   *  Ein vollständig konfiguriertes Filament\Infolists\Infolist-Objekt
   *  mit allen Abschnitten und Einträgen (Sections, TextEntry, ImageEntry).
   *
   * Hinweis:
   *  Die Methode kann erweitert werden, um weitere Hersteller-spezifische
   *  Felder, Medien oder technische Produktparameter auszugeben.
   *
   * @param  \Filament\Infolists\Infolist  $infolist
   * @return \Filament\Infolists\Infolist
   */
  public static function infolist(Infolist $infolist): Infolist
  {
      return $infolist
          ->schema([
              Section::make('Abmessungen')
                  ->schema([
                      TextEntry::make('dimensions_raw')
                          ->label('Rohmaße')
                          ->placeholder('-'),
                      TextEntry::make('dimension_length_mm')
                          ->label('Länge (mm)')
                          ->placeholder('-'),
                      TextEntry::make('dimension_width_mm')
                          ->label('Breite (mm)')
                          ->placeholder('-'),
                      TextEntry::make('dimension_height_mm')
                          ->label('Höhe (mm)')
                          ->placeholder('-'),
                  ])
                  ->visible(fn($record) =>
                      $record->dimensions_raw
                      || $record->dimension_length_mm
                      || $record->dimension_width_mm
                      || $record->dimension_height_mm
                  ),

              Section::make('Bilder')
                  ->schema([
                      ImageEntry::make('display_image_urls')
                          ->label('Produktbilder')
                          ->height(160)
                          ->stacked(), // Bilder untereinander statt nebeneinander
                  ])
                  ->visible(fn($record) => ! empty($record->display_image_urls)),
          ]);
  }

}
