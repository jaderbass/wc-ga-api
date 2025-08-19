<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturerResource\Pages;
use App\Filament\Resources\ManufacturerResource\RelationManagers;
use App\Models\Manufacturer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
/* use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Components\EncryptedPassword; */
use Illuminate\Support\Facades\Crypt;
/* use Filament\Forms\Components\TextInput;
use Nette\Utils\Html; */

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    /**
     * Formularschema für Create/Edit der Hersteller.
     *
     * Layout:
     * - „Allgemein“-Sektion mit Name, Website und Import-Typ (CSV/API) – Import-Typ steht neben Website.
     * - „API-Zugang“-Sektion (API-URL, Nutzer, Passwort, Token) wird nur sichtbar, wenn Import-Typ „API“ gewählt ist.
     *
     * Sicherheit:
     * - api_password wird nie vorbefüllt; bei Eingabe neu verschlüsselt gespeichert.
     *
     * @param \Filament\Forms\Form $form
     * @return \Filament\Forms\Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Allgemein')
                    ->schema([
                        Forms\Components\Grid::make(12)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Herstellername')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            Forms\Components\TextInput::make('manufacturercountry')
                                ->label('Ländercode')
                                // ->required()
                                ->maxLength(3)
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('website')
                                ->label('Website')
                                ->url()
                                ->maxLength(255)
                                ->columnSpan(3),

                            Forms\Components\Select::make('import_type')
                                ->label('Import-Typ')
                                ->native(false)
                                ->options([
                                    'csv' => 'CSV',
                                    'api' => 'API',
                                ])
                                ->default('csv')
                                ->required()
                                ->reactive()
                                ->helperText('Wähle „API“, wenn die Daten über eine Schnittstelle importiert werden.')
                                ->columnSpan(3),
                            Forms\Components\Textarea::make('notes')
                                ->label('Notizen')
                                ->columnSpan(6),
                                ]),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('API-Zugang')
                    ->description('Zugangsdaten nur angeben, wenn der Import-Typ „API“ ist.')
                    ->schema([
                        Forms\Components\Grid::make(12)->schema([
                            Forms\Components\TextInput::make('api_url')
                                ->label('API-URL')
                                ->url()
                                ->maxLength(255)
                                ->columnSpan(6),

                            Forms\Components\TextInput::make('api_user')
                                ->label('API-Nutzer')
                                ->maxLength(255)
                                ->columnSpan(3),

                            // API-Passwort: nie vorbefüllen, nur bei Eingabe verschlüsselt speichern
                            Forms\Components\TextInput::make('api_password')
                                ->label('API-Passwort')
                                ->password()
                                ->nullable()
                                ->helperText('Nur ausfüllen, wenn du das Passwort neu setzen möchtest. Leer lassen, um den bestehenden Wert zu behalten.')
                                ->afterStateHydrated(function (Forms\Components\TextInput $component): void {
                                    $component->state(''); // Feld immer leer anzeigen
                                })
                                ->dehydrateStateUsing(function (?string $state, ?\App\Models\Manufacturer $record): ?string {
                                    if (filled($state)) {
                                        return Crypt::encryptString($state);
                                    }
                                    // bei Create ist $record null -> bleibt null; bei Edit bleibt alter Wert erhalten
                                    return $record?->api_password;
                                })
                                ->dehydrated(true)
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('api_token')
                                ->label('API-Token')
                                ->maxLength(255)
                                ->columnSpan(6),
                        ]),
                    ])
                    // Sichtbar nur, wenn Import-Typ „api“ gewählt ist
                    ->visible(fn(Get $get) => $get('import_type') === 'api')
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->label('Hersteller')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturercountry')
                    ->label('Ländercode')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('website')->label('Website')->limit(30),
                Tables\Columns\TextColumn::make('api_url')->label('API-URL')->limit(30),
                Tables\Columns\TextColumn::make('import_type')->label('Import-Typ'),
                Tables\Columns\TextColumn::make('updated_at')->label('Letzte Änderung')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('import_type')
                    ->label('Import-Typ')
                    ->options([
                        'csv' => 'CSV',
                        'xml' => 'XML',
                        'api' => 'API',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            RelationManagers\ManufacturerAuditRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturers::route('/'),
            'create' => Pages\CreateManufacturer::route('/create'),
            'edit' => Pages\EditManufacturer::route('/{record}/edit'),
        ];
    }
}
