<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * UserResource
 *
 * Filament-Resource zur Verwaltung von Benutzerkonten.
 *
 * Funktionen:
 * - Anlegen, Bearbeiten, Löschen von Benutzern
 * - Zuweisen und Entfernen von Rollen (Spatie Permission)
 * - Filtern der Tabelle nach Rollen
 *
 * Voraussetzungen:
 * - User-Model nutzt Spatie\Permission\Traits\HasRoles
 * - $guard_name im User-Model ist auf 'web' gesetzt (oder passend zum Panel-Guard)
 *
 * @package App\Filament\Resources
 */
class UserResource extends Resource
{
    /** @var class-string<User> $model */
    protected static ?string $model = User::class;

    /** @var string|null Icon in der Navigation */
    protected static ?string $navigationIcon = 'heroicon-o-users';

    /** @var string|null Navigationslabel */
    protected static ?string $navigationLabel = 'Benutzer';

    /** @var string|null Pluralanzeige des Modells */
    protected static ?string $pluralModelLabel = 'Benutzer';

    /** @var string|null Einzelanzeige des Modells */
    protected static ?string $modelLabel = 'Benutzer';

    /**
     * Definiert das Formularschema für Erstellen/Bearbeiten.
     *
     * Felder:
     * - name: Pflichtfeld, max. 255 Zeichen
     * - email: Pflichtfeld, E-Mail-Format, eindeutig in 'users' (eigene ID beim Bearbeiten ignoriert)
     * - password: beim Anlegen Pflicht, beim Bearbeiten optional; Hashing via Hash::make,
     *             wird nur dehydriert, wenn Wert gesetzt ist
     * - passwordConfirmation: Wiederholung zum Schutz vor Tippfehlern (wird nicht gespeichert)
     * - roles: Mehrfachauswahl über Relationship (Spatie-Rollen), nicht required,
     *          damit Entfernen durch Abwählen möglich ist; gefiltert auf guard_name='web'
     *
     * @param  Form $form  Filament-Forminstanz
     * @return Form        Konfiguriertes Form-Objekt
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('E-Mail')
                ->email()
                ->required()
                ->maxLength(255)
                // Filament-Kürzel; ignoriert beim Bearbeiten den aktuellen Datensatz
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->label('Passwort')
                ->password()
                // Beim Erstellen Pflicht, beim Bearbeiten optional
                ->required(fn(string $operation) => $operation === 'create')
                // Nur hashen, wenn ein Wert übergeben wurde
                ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                // Nur dehydrieren (persistieren), wenn ein Wert gesetzt ist
                ->dehydrated(fn($state) => filled($state))
                ->helperText('Nur ausfüllen, wenn ein neues Passwort gesetzt werden soll.'),

            TextInput::make('passwordConfirmation')
                ->label('Passwort (Wiederholung)')
                ->password()
                ->same('password')
                // Wird nicht gespeichert
                ->dehydrated(false)
                ->required(fn(string $operation) => $operation === 'create'),

            Select::make('roles')
                ->label('Rollen')
                ->multiple()
                ->preload()
                ->searchable()
                ->native(false)
                // Wichtig: Relationship übernimmt Laden/Speichern per IDs (kein ->options())
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn($query) => $query->where('guard_name', 'web')
                ),
        ]);
    }

    /**
     * Definiert die Tabellenspalten, Filter und Aktionen der Listenansicht.
     *
     * Spalten:
     * - name, email
     * - roles.name als Badges (kommasepariert)
     *
     * Filter:
     * - Rollenfilter über Relationship (gefiltert auf guard_name = 'web')
     *
     * Aktionen:
     * - Bearbeiten, Löschen
     * - Massenlöschung
     *
     * @param  Table $table  Filament-Tabelleninstanz
     * @return Table         Konfiguriertes Table-Objekt
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-Mail')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rollen')
                    ->badge()
                    ->separator(', ')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rolle')
                    ->multiple()
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn($q) => $q->where('guard_name', 'web')
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    /**
     * Definiert die zugehörigen Seitenrouten (Index, Create, Edit).
     *
     * @return array<string, string> Assoziatives Array der Seitenrouten
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
