<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Spatie\Permission\Models\Role;
use App\Filament\Resources\UserResource\Pages;

/**
 * Filament-Resource für Benutzerverwaltung.
 *
 * Ermöglicht Admins:
 * - Benutzer anlegen, bearbeiten, löschen
 * - Rollen direkt über Dropdown verwalten
 * - Benutzer nach Rollen filtern
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Benutzer';
    protected static ?string $pluralModelLabel = 'Benutzer';
    protected static ?string $modelLabel = 'Benutzer';

    /**
     * Formular zum Anlegen/Bearbeiten von Benutzern.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('password')
                    ->label('Passwort')
                    ->password()
                    ->dehydrateStateUsing(fn($state) => ! empty($state) ? bcrypt($state) : null)
                    ->dehydrated(fn($state) => filled($state))
                    ->maxLength(255)
                    ->helperText('Nur ausfüllen, wenn ein neues Passwort gesetzt werden soll.'),

                Select::make('roles')
                    ->label('Rollen')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->options(Role::query()->pluck('name', 'name'))
                    ->preload()
                    ->searchable()
                    ->required(),
            ]);
    }

    /**
     * Tabelle zur Anzeige von Benutzern.
     *
     * @param Table $table
     * @return Table
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

                // Neue Darstellung der Rollen als Badges
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rollen')
                    ->badge()
                    ->separator(', ')
                    ->sortable(),
            ])
            ->filters([
                // Filter nach Rollen
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rolle')
                    ->multiple()
                    ->options(Role::query()->pluck('name', 'name'))
                    ->query(function ($query, array $data) {
                        if (! empty($data['values'])) {
                            $query->whereHas('roles', function ($q) use ($data) {
                                $q->whereIn('name', $data['values']);
                            });
                        }
                    }),
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
     * Seiten (Listen-, Erstellen-, Bearbeitungsseite).
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
