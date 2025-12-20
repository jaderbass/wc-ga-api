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
use Filament\Forms;
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
              TextInput::make('dimensions_raw')
                ->label('Rohmaß (importiert)')
                ->helperText('Originale Herstellerangabe, z. B. "10 x 20 x 30 cm"')
                ->columnSpan(12)
                ->disabled(),

              TextInput::make('dimension_length_mm')
                ->label('Länge (mm)')
                ->numeric()
                ->helperText('Länge in Millimeter')
                ->columnSpan(3),

              TextInput::make('dimension_width_mm')
                ->label('Breite (mm)')
                ->numeric()
                ->helperText('Breite in Millimeter')
                ->columnSpan(3),

              TextInput::make('dimension_height_mm')
                ->label('Höhe (mm)')
                ->numeric()
                ->helperText('Höhe in Millimeter')
                ->columnSpan(3),

              TextInput::make('weight')
                ->label('Gewicht (g)')
                ->numeric()
                ->helperText('Gewicht in Gramm')
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

              Forms\Components\Placeholder::make('author_display')
                ->label('Autor (Import)')
                ->content(function ($record): string {
                  if (! $record || ! $record->author) {
                    return '–';
                  }

                  $user  = $record->author;
                  $name  = trim((string) ($user->name ?? ''));
                  $email = (string) ($user->email ?? '');

                  if ($name !== '' && $email !== '') {
                    return $name . ' (' . $email . ')';
                  }

                  if ($name !== '') {
                    return $name;
                  }

                  if ($email !== '') {
                    return $email;
                  }

                  return '–';
                })
                ->columnSpan(6),

              Forms\Components\Placeholder::make('author_id_display')
                ->label('Author-ID')
                ->content(function ($record): string {
                  if (! $record || ! $record->author_id) {
                    return '–';
                  }

                  return (string) $record->author_id;
                })
                ->columnSpan(3),

              Forms\Components\Placeholder::make('author_hint')
                ->label('Hinweis')
                ->content('Der Autor wird beim ersten Import automatisch aus dem aktuell angemeldeten Benutzer gesetzt und bei späteren Aktualisierungen nicht überschrieben.')
                ->columnSpan(12),

            ]) // Grid
          ]) //schema
          ->collapsible(),

        FormSection::make('Hersteller-Produktdetails (readonly)')
          ->description('Zusätzliche Produkt-Metadaten aus Hersteller-Importen (z.B. Petzl).')
          ->schema([
            Grid::make(12)->schema([
              Placeholder::make('designation')
                ->label('Bezeichnung')
                ->content(fn($record) => $record?->designation ?: '–')
                ->columnSpan(6),

              Placeholder::make('type')
                ->label('Typ')
                ->content(fn($record) => $record?->type ?: '–')
                ->columnSpan(6),

              Placeholder::make('category')
                ->label('Kategorie')
                ->content(fn($record) => $record?->category ?: '–')
                ->columnSpan(6),

              Placeholder::make('subcategory')
                ->label('Unterkategorie')
                ->content(fn($record) => $record?->subcategory ?: '–')
                ->columnSpan(6),

              Placeholder::make('market')
                ->label('Markt')
                ->content(fn($record) => $record?->market ?: '–')
                ->columnSpan(6),

              Placeholder::make('customs')
                ->label('Zolltarifnummer')
                ->content(fn($record) => $record?->customs ?: '–')
                ->columnSpan(6),

              Placeholder::make('made_in')
                ->label('Herkunft (Made in)')
                ->content(fn($record) => $record?->made_in ?: '–')
                ->columnSpan(6),

              Placeholder::make('materials')
                ->label('Materialien')
                ->content(fn($record) => $record?->materials ?: '–')
                ->columnSpan(12),
            ]),
          ])
          ->columnSpan(12)
          ->collapsible()
          ->collapsed()
          ->visible(function ($record): bool {
            if (! $record) {
              return false;
            }

            return (bool) (
              $record->designation
              || $record->type
              || $record->category
              || $record->subcategory
              || $record->market
              || $record->customs
              || $record->made_in
              || $record->materials
            );
          }),

        FormSection::make('Hersteller-Maße & Gewicht (readonly)')
          ->description('Maße und Gewicht laut Herstellerangaben (z.B. aus Petzl-Varianten).')
          ->schema([
            Grid::make(12)->schema([
              Placeholder::make('manufacturer_dimensions')
                ->label('Abmessungen (L × B × H)')
                ->content(function ($record) {
                  if (! $record) {
                    return '–';
                  }

                  // erste Variante als Referenz verwenden
                  $variation = $record->variations()->orderBy('id')->first();

                  if (! $variation) {
                    return '–';
                  }

                  // Feldnamen bei Bedarf anpassen
                  $lengthMm = $variation->length_mm ?? null;
                  $widthMm  = $variation->width_mm ?? null;
                  $heightMm = $variation->height_mm ?? null;

                  $parts = [];

                  if ($lengthMm) {
                    $parts[] = number_format($lengthMm / 1000, 2, ',', '') . ' m';
                  }

                  if ($widthMm) {
                    $parts[] = number_format($widthMm / 1000, 2, ',', '') . ' m';
                  }

                  if ($heightMm) {
                    $parts[] = number_format($heightMm / 1000, 2, ',', '') . ' m';
                  }

                  if ($parts === []) {
                    return '–';
                  }

                  return implode(' × ', $parts);
                })
                ->columnSpan(6),

              Placeholder::make('manufacturer_weight')
                ->label('Gewicht (pro Variante)')
                ->content(function ($record) {
                  if (! $record) {
                    return '–';
                  }

                  $variation = $record->variations()->orderBy('id')->first();

                  if (! $variation) {
                    return '–';
                  }

                  // Feldnamen bei Bedarf anpassen
                  $weightGr = $variation->weight ?? null;

                  if (! $weightGr) {
                    return '–';
                  }

                  $kg = $weightGr / 1000;

                  return number_format($kg, 2, ',', '') . ' kg';
                })
                ->columnSpan(6),
            ]),
          ])
          ->columnSpan(12),

        FormSection::make('Produktbilder (readonly)')
          ->description('Produktbilder aus Herstellerdaten (nur Anzeige)')
          ->schema([
            Grid::make(12)->schema([

              // Bild-Galerie
              Placeholder::make('image_gallery')
                ->hiddenLabel()
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
                ->columnSpan(12),
              // ->disableLabel(),
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
              ->maxSize(25000) // KB => 25 MB
              ->visible(fn($get) => $get('sourceType') === 'csv')
              ->storeFiles(false),

            FileUpload::make('xml')
              ->label('XML-Datei')
              ->acceptedFileTypes(['text/xml', 'application/xml'])
              ->visible(fn($get) => $get('sourceType') === 'xml')
              ->storeFiles(false),
          ])
          ->action(function (array $data, Tables\Actions\Action $action) {
            $manufacturerId = (int)($data['manufacturer_id'] ?? 0);
            $manufacturer   = \App\Models\Manufacturer::findOrFail($manufacturerId);

            // Fallback: Wenn im Formular nichts gesetzt ist, nimm den Import-Typ aus der Hersteller-Tabelle
            $sourceType = $data['sourceType'] ?? $manufacturer->import_type ?? 'csv';

            // CSV/XML: Datei ist Pflicht
            if (in_array($sourceType, ['csv', 'xml'], true) && empty($data[$sourceType] ?? null)) {
              Notification::make()
                ->title('Bitte wählen Sie eine Datei für den Import aus.')
                ->danger()
                ->send();
              return;
            }

            try {
              // Source je nach Typ bestimmen
              $source = match ($sourceType) {
                'csv', 'xml' => Storage::disk('local')->putFile('imports', $data[$sourceType]),
                'api'        => (string) $manufacturer->api_url,
                default      => '',
              };

              if ($sourceType === 'api' && $source === '') {
                Notification::make()
                  ->title('Keine API-URL hinterlegt')
                  ->body('Für diesen Hersteller ist keine API-URL in den Stammdaten hinterlegt.')
                  ->danger()
                  ->send();

                $action->halt();
                return;
              }

              if ($sourceType !== 'api' && $source === '') {
                Notification::make()
                  ->title('Import-Quelle fehlt')
                  ->body('Die Datei konnte nicht gespeichert werden.')
                  ->danger()
                  ->send();
                return;
              }

              // Für CSV/XML: echten absoluten Pfad übergeben
              $payloadSource = $sourceType === 'api'
                ? $source
                : storage_path("app/{$source}");

              // Job NACH Response laufen lassen (verhindert 500 durch lange Request)
              \App\Jobs\RunManufacturerImportJob::dispatch(
                $manufacturerId,
                $sourceType,
                $payloadSource
              )->afterResponse();

              Notification::make()
                ->title('Import gestartet')
                ->body('Der Import läuft im Hintergrund. Du kannst die Seite verlassen.')
                ->success()
                ->send();

              $action->success();   // signalisiert Livewire "fertig"
              $action->cancel();    // schließt Modal zuverlässig

              return;
            } catch (\Throwable $e) {
              Log::error('ACTION_EXCEPTION', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
              ]);

              Notification::make()
                ->title('Import fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->send();

              $action->failure();
              if (config('app.debug')) {
                throw $e;
              }
            }
          })
          ->closeModalByClickingAway(false)
          ->modalSubmitActionLabel('Import starten')
          ->successNotificationTitle('Import gestartet'),
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
          ->visible(
            fn($record) =>
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

        Section::make('Petzl-Produktdetails')
          // nur anzeigen, wenn Hersteller Petzl ist
          ->visible(fn(\App\Models\Product $record) => $record->manufacturer?->name === 'Petzl')
          ->columns(2)
          ->schema([
            TextEntry::make('designation')
              ->label('Bezeichnung')
              ->placeholder('-'),

            TextEntry::make('type')
              ->label('Typ')
              ->placeholder('-'),

            TextEntry::make('category')
              ->label('Kategorie')
              ->placeholder('-'),

            TextEntry::make('subcategory')
              ->label('Unterkategorie')
              ->placeholder('-'),

            TextEntry::make('market')
              ->label('Markt')
              ->placeholder('-'),

            TextEntry::make('customs')
              ->label('Zolltarifnummer')
              ->placeholder('-'),

            TextEntry::make('made_in')
              ->label('Herkunft (Made in)')
              ->placeholder('-'),

            TextEntry::make('certification')
              ->label('Zertifizierung')
              ->placeholder('-'),

            TextEntry::make('materials')
              ->label('Materialien')
              ->columnSpanFull()
              ->placeholder('-'),
          ]),
      ]);
  }
}
