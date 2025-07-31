<?php

namespace App\Filament\Resources\ManufacturerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * RelationManager zur Anzeige der Audit-Logs für Hersteller.
 *
 * Stellt alle Änderungen (Feld, Alter Wert, Neuer Wert, Änderungsdatum, Benutzer) in einer Tabelle dar.
 */
class ManufacturerAuditRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    /**
     * Definiert die Tabelle für die Audit-Datensätze.
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('field')
                    ->label('Geändertes Feld')
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_value')
                    ->label('Alter Wert')
                    ->wrap(),
                Tables\Columns\TextColumn::make('new_value')
                    ->label('Neuer Wert')
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Geändert von')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Geändert am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
