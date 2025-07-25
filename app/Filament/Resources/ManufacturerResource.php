<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManufacturerResource\Pages;
use App\Filament\Resources\ManufacturerResource\RelationManagers;
use App\Models\Manufacturer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Components\EncryptedPassword;

class ManufacturerResource extends Resource
{
    protected static ?string $model = Manufacturer::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('manufacturer')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('manufacturercountry')
                    ->label('Ländercode')
                    ->required()
                    ->maxLength(3),
                Forms\Components\TextInput::make('website')
                    ->label('Website')
                    ->url(),
                Forms\Components\TextInput::make('api_url')
                    ->label('API-URL'),
                Forms\Components\TextInput::make('api_user')
                    ->label('API-Benutzername')
                    ->maxLength(255),
                EncryptedPassword::make('api_password')
                    ->label('API-Passwort')
                    ->maxLength(255),
                Forms\Components\TextInput::make('api_token')
                    ->label('API-Token')
                    ->password(),
                Forms\Components\Select::make('import_type')
                    ->label('Import-Typ')
                    ->options([
                        'csv' => 'CSV',
                        'xml' => 'XML',
                        'api' => 'API',
                    ]),
                Forms\Components\Textarea::make('notes')
                    ->label('Notizen'),

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
            //
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
