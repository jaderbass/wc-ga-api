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
use App\Models\ImportRun;
use App\Services\ProductNaming\DefaultProductNameBuilder;
use App\Services\ProductNaming\ProductNameContext;
use App\Support\TextNormalizer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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
    protected static ?string $navigationLabel = 'Produkte';

    protected static ?string $modelLabel = 'Produkt';
    protected static ?string $pluralModelLabel = 'Produkte';


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
                                ->columnSpan(8),

                            Placeholder::make('baugruppe_label')
                                ->label('Baugruppe')
                                ->content(function (?Product $record): string {
                                    return match ($record?->baugruppe) {
                                        1 => '1 - Helm',
                                        2 => '2 - Gurt',
                                        default => '—',
                                    };
                                })
                                ->columnSpan(4),

                            Placeholder::make('product_name_parts')
                                ->label('Namensbestandteile')
                                ->content(function ($record) {
                                    if (! $record) {
                                        return '—';
                                    }

                                    $ctx = ProductNameContext::fromProduct($record);

                                    // Basis-Infos (immer anzeigen)
                                    $items = [
                                        ['Manufacturer', $ctx->manufacturerName],
                                        ['Original', $ctx->designation],
                                        ['Category', $ctx->categoryName !== '' ? $ctx->categoryName : '(folgt)'],
                                    ];

                                    // optionale Properties nur bei echtem Inhalt
                                    foreach ([$ctx->properties[0] ?? null, $ctx->properties[1] ?? null, $ctx->properties[2] ?? null] as $idx => $prop) {
                                        if (filled($prop)) {
                                            $items[] = ['p' . ($idx + 1), $prop];
                                        }
                                    }

                                    $parts = [];

                                    foreach ($items as [$label, $value]) {
                                        $labelEsc = e((string) $label);
                                        $valueEsc = e((string) $value);

                                        $parts[] = <<<HTML
<span style="display:inline-flex;align-items:baseline;gap:6px;min-width:0;">
  <span style="
    font-size:10px;
    line-height:1;
    font-weight:600;
    letter-spacing:.03em;
    text-transform:uppercase;
    padding:2px 6px;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.25);
    color:rgba(148,163,184,.95);
    white-space:nowrap;
  ">{$labelEsc}</span>

  <span style="
    font-size:13px;
    opacity:.9;
    word-break:break-word;
    min-width:0;
  ">{$valueEsc}</span>
</span>
HTML;
                                    }

                                    $html = '<div style="display:flex;flex-wrap:wrap;gap:6px 10px;">'
                                        . implode(' - ', $parts)
                                        . '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->helperText('Live Vorschau basierend auf den aktuellen Daten')
                                ->columnSpanFull(),


                            TextInput::make('slug')
                                ->label('Slug')
                                ->helperText('URL-Teil, automatisch aus dem Namen. Kollisionen werden serverseitig aufgelöst.')
                                ->required()
                                ->columnSpan(4),

                            /* Select::make('manufacturer_id')
                ->required()
                ->relationship('manufacturer', 'manufacturer')
                ->native(false)        // Tom Select statt nativer <select>
                ->searchable()         // Typeahead-Suche aktivieren
                ->preload()            // Optionen vorladen (besseres UX im Modal)
                ->columnSpan(4), */

                            TextInput::make('manufacturer_readonly')
                                ->label('Hersteller')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn($state, ?Product $record) => $record?->manufacturer?->manufacturer ?? '—')
                                ->columnSpan(4),

                            Placeholder::make('categories_display')
                                ->label('Kategorien')
                                ->content(function (?Product $record): HtmlString|string {
                                    if (! $record) {
                                        return '—';
                                    }

                                    $categories = $record->categories()
                                        ->orderBy('name')
                                        ->pluck('name')
                                        ->all();

                                    if ($categories === []) {
                                        return '—';
                                    }

                                    $badges = collect($categories)
                                        ->map(fn(string $name): string => sprintf(
                                            '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-200 mr-1 mb-1">%s</span>',
                                            e($name)
                                        ))
                                        ->implode('');

                                    return new HtmlString('<div class="flex flex-wrap gap-1">' . $badges . '</div>');
                                }),

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

                FormSection::make('Produktname (Vorschau)')
                    ->description('Live Vorschau basierend auf den aktuellen Daten')
                    ->schema([
                        Placeholder::make('product_name_preview')
                            ->label('Generierter Produktname')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }

                                $ctx = ProductNameContext::fromProduct($record);
                                $builder = app(DefaultProductNameBuilder::class);

                                // return $builder->build($ctx) ?? '—';
                                $result = $builder->build($ctx);

                                return $result->productName ?? '—';
                            }),

                        Placeholder::make('product_name_parts')
                            ->label('Namensbestandteile')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '—';
                                }

                                $ctx = ProductNameContext::fromProduct($record);

                                return implode(' | ', array_filter([
                                    'Manufacturer: ' . $ctx->manufacturerName,
                                    'Original: ' . $ctx->designation,
                                    'Category: ' . ($ctx->categoryName !== '' ? $ctx->categoryName : '—'),
                                    'p1: ' . ($ctx->properties[0] ?? '—'),
                                    'p2: ' . ($ctx->properties[1] ?? '—'),
                                    'p3: ' . ($ctx->properties[2] ?? '—'),
                                ]));
                            }),
                    ])
                    ->collapsed(),

                FormSection::make('Beschreibungen')
                    ->description('Weiterführende Produktinformationen')
                    ->schema([
                        Grid::make(12)->schema([
                            Group::make()
                                ->schema([
                                    Placeholder::make('short_description_html')
                                        ->label('')
                                        ->content(
                                            fn($record) =>
                                            new HtmlString(
                                                '<h3 class="text-base font-semibold mb-2">Kurzbeschreibung</h3>'
                                                    . ($record?->short_description ?: '<div class="text-gray-500">—</div>')
                                            )
                                        )
                                        ->columnSpanFull(),

                                    Placeholder::make('description_html')
                                        ->label('')
                                        ->content(
                                            fn($record) =>
                                            new HtmlString(
                                                '<h3 class="text-base font-semibold mt-4 mb-2">Beschreibung</h3>'
                                                    . ($record?->description ?: '<div class="text-gray-500">—</div>')
                                            )
                                        )
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),

                        ]) // Grid
                    ]) //schema
                    ->collapsible(),


                /* FormSection::make('Maße')
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
          ->collapsible(), */

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
                        ]), // Grid
                        // technical_attributes_readonly Repeater -> Tabelle (readonly)
                        \Filament\Forms\Components\Placeholder::make('technical_features_table')
                            ->label('Technische Angaben')
                            ->content(function (?Product $record) {
                                if (! $record) {
                                    return new HtmlString('—');
                                }

                                $items = $record->meta()
                                    ->where('scope', 'product')
                                    ->where('key', 'like', 'feature.%')
                                    ->orderBy('key')
                                    ->get(['key', 'value'])
                                    ->map(function ($m) {
                                        $label = (string) $m->key;
                                        $label = preg_replace('/^feature\./', '', $label) ?? $label;
                                        $label = str_replace('_', ' ', $label);
                                        $label = trim($label);
                                        $label = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
                                        // optionale Feinschliff-Korrekturen
                                        $label = str_replace(
                                            [' Mm', ' Kn', ' Uiaa'],
                                            [' mm', ' kN', ' UIAA'],
                                            $label
                                        );


                                        $value = is_string($m->value) ? trim($m->value) : (string) $m->value;

                                        return [$label, $value];
                                    })
                                    ->filter(fn($pair) => ($pair[1] ?? '') !== '')
                                    ->values()
                                    ->all();

                                if (empty($items)) {
                                    return new HtmlString('—');
                                }

                                $rows = '';
                                foreach ($items as [$label, $value]) {
                                    $rows .= '<tr>'
                                        . '<td style="padding:6px 10px;border-bottom:1px solid #e5e7eb45;white-space:nowrap;"><strong>' . e($label) . '</strong></td>'
                                        . '<td style="padding:6px 10px;border-bottom:1px solid #e5e7eb45;">' . e($value) . '</td>'
                                        . '</tr>';
                                }

                                return new HtmlString(
                                    '<div style="overflow:auto;">'
                                        . '<table style="width:100%;border-collapse:collapse;">'
                                        . '<thead><tr>'
                                        . '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #e5e7eb9d;">Attribut</th>'
                                        . '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #e5e7eb9d;">Wert</th>'
                                        . '</tr></thead>'
                                        . '<tbody>' . $rows . '</tbody>'
                                        . '</table>'
                                        . '</div>'
                                );
                            })

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

                                    if (! $record) {
                                        return new HtmlString('<p class="text-sm text-gray-500">Keine Bilder vorhanden.</p>');
                                    }

                                    $pathsMeta = $record->meta()
                                        ->where('scope', 'product')
                                        ->where('key', 'aliens_image_paths')
                                        ->whereNull('variation_id')
                                        ->first();

                                    $urls = [];

                                    if ($pathsMeta && is_string($pathsMeta->value) && trim($pathsMeta->value) !== '') {
                                        $paths = json_decode($pathsMeta->value, true);

                                        if (is_array($paths)) {
                                            foreach ($paths as $path) {
                                                if (!is_string($path) || trim($path) === '') continue;

                                                $path = ltrim(trim($path), '/'); // products/aliens/m1/p6/5191.jpg

                                                if (preg_match('#^products/aliens/m(\d+)/p(\d+)/(.+)$#', $path, $m)) {
                                                    $urls[] = url("/aliens-image/{$m[1]}/{$m[2]}/{$m[3]}");
                                                }
                                            }
                                        }
                                    }

                                    // Fallback: Remote URLs, falls paths fehlen
                                    if ($urls === []) {
                                        $urlsMeta = $record->meta()
                                            ->where('scope', 'product')
                                            ->where('key', 'aliens_image_urls')
                                            ->whereNull('variation_id')
                                            ->first();

                                        if ($urlsMeta && is_string($urlsMeta->value) && trim($urlsMeta->value) !== '') {
                                            $decoded = json_decode($urlsMeta->value, true);
                                            if (is_array($decoded)) {
                                                foreach ($decoded as $u) {
                                                    if (is_string($u) && trim($u) !== '') $urls[] = trim($u);
                                                }
                                            }
                                        }
                                    }

                                    $urls = array_values(array_unique($urls));


                                    // $urls = $localUrls !== [] ? $localUrls : ($record->display_image_urls ?? []);

                                    if (empty($urls)) {
                                        return new HtmlString('<p class="text-sm text-gray-500">Keine Bilder vorhanden.</p>');
                                    }

                                    $html = '<div class="flex flex-wrap gap-4">';

                                    foreach ($urls as $url) {
                                        $urlEsc = e($url);

                                        $html .= <<<HTML
      <div style="width:140px;">
        <div style="width:140px;height:140px;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid #e5e7eb;border-radius:8px;background:#111827;">
          <img
            src="{$urlEsc}"
            style="width:140px;height:auto;object-fit:contain;"
            loading="lazy"
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
                    ->searchable(['product_name', 'product_number'])
                    ->sortable()
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('manufacturer.manufacturer')
                    ->label('Hersteller')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                /* Tables\Columns\TextColumn::make('product_number')
          ->label('Artikelnummer')
          ->searchable()
          ->sortable()
          ->toggleable(),

        Tables\Columns\TextColumn::make('ean')
          ->label('EAN')
          ->searchable()
          ->sortable()
          ->toggleable(), */

                // NEU: hart auf 45 Zeichen begrenzen + Tooltip mit vollem Text
                Tables\Columns\TextColumn::make('short_description')
                    ->label('Kurzbeschreibung')
                    // 1) State aus Record ableiten: short_description ODER Fallback auf description
                    ->state(fn($record) => $record->short_description ?: $record->description)
                    // 2) Anzeige: HTML entfernen + kürzen
                    ->formatStateUsing(
                        fn($state) =>
                        $state ? Str::limit(TextNormalizer::plain((string) $state), 40) : '—'
                    )
                    // 3) Tooltip: voller Text, ebenfalls ohne HTML
                    ->tooltip(
                        fn($record) => ($text = ($record->short_description ?: $record->description))
                            ? TextNormalizer::plain((string) $text)
                            : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('size')
                    ->label('Größe')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->relationship('manufacturer', 'manufacturer', fn($query) => $query->where('active', true)) // Relation + anzuzeigendes Feld
                    ->multiple()                                   // ⬅️ Mehrfachauswahl aktivieren
                    ->searchable()
                    ->preload()
                    ->indicator('Hersteller'),                     // hübscher Filter-Badge-Text
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('rebuildName')
                    ->label('')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Produktnamen basierend auf aktuellen Daten neu generieren')
                    ->size('lg')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        /** @var \App\Models\Product $record */

                        $ctx = ProductNameContext::fromProduct($record);

                        /** @var \App\Services\ProductNaming\DefaultProductNameBuilder $builder */
                        $builder = app(DefaultProductNameBuilder::class);

                        $result = $builder->build($ctx);

                        $record->update([
                            'product_name' => $result->productName,
                        ]);

                        Notification::make()
                            ->title('Produktname aktualisiert')
                            ->success()
                            ->send();
                    }),
            ])

            ->headerActions([
                Tables\Actions\Action::make('importProducts')
                    ->label('Import starten')
                    ->form([
                        Select::make('manufacturer_id')
                            ->label('Hersteller')
                            ->placeholder('Bitte Hersteller wählen …')
                            ->relationship('manufacturer', 'manufacturer', fn($query) => $query->where('active', true))
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
                    ->action(function (array $data, Tables\Actions\Action $action, \Livewire\Component $livewire) {
                        $manufacturerId = (int) $data['manufacturer_id'];
                        $manufacturer   = \App\Models\Manufacturer::findOrFail($manufacturerId);

                        $sourceType = $data['sourceType'] ?? $manufacturer->import_type ?? 'csv';

                        // Pflichtprüfung
                        if (in_array($sourceType, ['csv', 'xml'], true) && empty($data[$sourceType] ?? null)) {
                            Notification::make()
                                ->title('Bitte wählen Sie eine Datei für den Import aus.')
                                ->danger()
                                ->send();

                            $action->halt();
                            return;
                        }

                        // Quelle bestimmen
                        $source = match ($sourceType) {
                            'csv', 'xml' => Storage::disk('local')->putFile('imports', $data[$sourceType]),
                            'api'        => (string) $manufacturer->api_url,
                            default      => null,
                        };

                        if (! $source) {
                            Notification::make()
                                ->title('Import-Quelle fehlt')
                                ->danger()
                                ->send();

                            $action->halt();
                            return;
                        }

                        $payloadSource = $sourceType === 'api'
                            ? $source
                            : storage_path("app/{$source}");

                        Log::info('Import dispatch', [
                            'manufacturerId' => $manufacturerId,
                            'sourceType'     => $sourceType,
                            'source'         => $source,
                            'payloadSource'  => $payloadSource,
                            'connection'     => config('queue.default'),
                            'queue_name'     => 'imports',
                        ]);

                        $exists = ($sourceType !== 'api') ? file_exists($payloadSource) : null;

                        Log::info('Import source prepared', [
                            'manufacturer_id' => $manufacturerId,
                            'source_type'     => $sourceType,
                            'source'          => $source,
                            'payloadSource'   => $payloadSource,
                            'exists'          => $exists,
                        ]);

                        $authorId = Auth::id();

                        $run = ImportRun::create([
                            'id'              => (string) Str::uuid(),
                            'manufacturer_id' => $manufacturerId,
                            'source_type'     => $sourceType,
                            'source'          => $payloadSource,
                            'author_id'       => Auth::id(),
                            'status'          => 'queued',
                        ]);

                        // 🔥 Job starten – sonst nichts
                        \App\Jobs\RunManufacturerImportJob::dispatch(
                            $manufacturerId,
                            $sourceType,
                            $payloadSource,
                            $run->author_id,
                            $run->id,
                        )
                            ->onConnection(config('queue.default', 'database'))
                            ->onQueue('imports');

                        $livewire->dispatch('import-run-started', runId: $run->id);

                        // ✅ DAS ist entscheidend
                        $action->success();
                    })
                    ->successNotificationTitle('Import gestartet')
                    ->closeModalByClickingAway(false)
                    ->modalSubmitActionLabel('Import starten'),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Löschen'),
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
                    ->visible(fn(Product $record) => $record->manufacturer?->name === 'Petzl')
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
