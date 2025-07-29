<?php

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

class ProductResource extends Resource
{
  protected static ?string $model = Product::class;

  protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Forms\Components\Select::make('manufacturer_id')
          ->required()
          ->relationship('manufacturer', 'manufacturer')
          ->columnSpanFull(),
        Forms\Components\TextInput::make('productnumber')
          ->maxLength(100)
          ->columnSpan(3),
        Forms\Components\TextInput::make('eancode')
          ->maxLength(14)
          ->columnSpan(3),
        Forms\Components\TextInput::make('skucode')
          ->maxLength(32)
          ->columnSpan(3),
        Forms\Components\TextInput::make('productname')
          ->required()
          ->maxLength(100)
          ->columnSpan(3),
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
        Forms\Components\TextInput::make('regularprice')
          ->required()
          ->numeric()
          ->integer()
          ->columnSpan(2),
        Forms\Components\TextInput::make('saleprice')
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
        Forms\Components\TextInput::make('unitprice')
          ->numeric()
          ->integer()
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('pcsperbox')
          ->numeric()
          ->integer()
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('boxwidth')
          ->numeric()
          ->integer()
          ->helperText('Box width in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('boxlength')
          ->numeric()
          ->integer()
          ->helperText('Box length in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
        Forms\Components\TextInput::make('boxheight')
          ->numeric()
          ->integer()
          ->helperText('Box height in mm')
          ->columnSpan(2)
          ->hidden(fn(Get $get): bool => $get('unit')),
      ])
      ->columns(12);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        Tables\Columns\TextColumn::make('productnumber')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('eancode')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('skucode')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('productname')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('description')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('shortdescription')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('price')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('regularprice')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('saleprice')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('width')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('length')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('height')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('unit')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('unitprice')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('pcsperbox')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('boxwidth')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('boxlength')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('boxheight')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('mpn')
          ->searchable()
          ->sortable(),
        Tables\Columns\TextColumn::make('weight')
          ->searchable()
          ->sortable(),
      ])
      ->filters([
        //
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
                ->afterStateUpdated(function (callable $set, $state) {
                    $importType = \App\Models\Manufacturer::find($state)?->import_type;
                    $set('import_type', $importType);
                })
                ->required(),

            Forms\Components\Hidden::make('import_type'),

            Forms\Components\FileUpload::make('file')
                ->label('Datei')
                ->storeFiles(false)
                ->visible(fn($get) => in_array($get('import_type'), ['csv', 'xml'])),
          ])
          ->action(function (array $data) {
            $config = \App\Services\ImporterSelector::forManufacturer($data['manufacturer_id']);
            $importer = $config['importer'];
            $type = $config['type'];

            // Debug-Log
            Log::info('Import gestartet', [
                'manufacturer_id' => $data['manufacturer_id'],
                'import_type' => $type,
                'api_url' => $config['api_url'] ?? null,
            ]);

            // Notification Debug
            \Filament\Notifications\Notification::make()
                ->title("Import gestartet ({$type})")
                ->body("Hersteller-ID: {$data['manufacturer_id']}" . 
                    ($type === 'api' ? "\nAPI: {$config['api_url']}" : ''))
                ->success()
                ->send();

        // Logik nach Typ
        $action = match ($type) {
          'csv' => function () use ($data, $importer) {
            $storedPath = Storage::disk('local')->putFile('imports', $data['file']);
            Log::info("CSV-Datei gespeichert unter {$storedPath}");
            $importer->handleUploadedFile($storedPath);
          },
          'xml' => function () use ($data, $importer) {
            $storedPath = Storage::disk('local')->putFile('imports', $data['file']);
            Log::info("XML-Datei gespeichert unter {$storedPath}");
            $importer->handleUploadedXmlFile($storedPath);
          },
          'api' => function () use ($config, $importer) {
            Log::info("Starte API-Import von {$config['api_url']}");
            $importer->handleFromUrl(
              $config['api_url'],
              $config['api_user'],
              $config['api_password']
            );
          },
          default => function () use ($type) {
            throw new \Exception("Unbekannter Import-Typ: {$type}");
          },
        };

        $count = $action();

        Log::info("Import beendet: {$count} Datensätze verarbeitet.");
        \Filament\Notifications\Notification::make()
          ->title('Import erfolgreich')
          ->body("Es wurden {$count} Datensätze verarbeitet.")
          ->success()
          ->send();



        \Filament\Notifications\Notification::make()
                ->title('Import erfolgreich gestartet')
                ->success()
                ->send();
        }),
    ])
    ;
  }

  public static function getRelations(): array
  {
    return [
      //
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
