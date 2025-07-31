<?php

namespace App\Filament\Resources\ManufacturerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ManufacturerAuditRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';
    protected static ?string $title = 'Änderungsprotokoll';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('field')
                    ->label('Geändertes Feld')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('old_value')
                    ->label('Alter Wert')
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('new_value')
                    ->label('Neuer Wert')
                    ->limit(50)
                    ->wrap()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Geändert von')
                    ->default('System')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Geändert am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Benutzer')
                    ->relationship('user', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Keine Änderungen gefunden')
            ->emptyStateDescription('Für diesen Hersteller wurden bisher keine Änderungen protokolliert.');
    }
}
