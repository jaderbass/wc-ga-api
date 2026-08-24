<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PetzlCategoryMappingResource\Pages;
use App\Filament\Resources\PetzlCategoryMappingResource\RelationManagers;
use App\Models\PetzlCategoryMapping;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PetzlCategoryMappingResource extends Resource
{
    protected static ?string $model = PetzlCategoryMapping::class;

    protected static ?string $navigationGroup = 'Petzl';

    protected static ?string $navigationLabel = 'Kategorie-Übersetzungen';

    protected static ?string $modelLabel = 'Petzl-Kategorie';

    protected static ?string $pluralModelLabel = 'Petzl-Kategorien';

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('CSV-Werte')
                    ->schema([
                        Forms\Components\TextInput::make('source_category')
                            ->label('CSV-Kategorie')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('source_subcategory')
                            ->label('CSV-Subkategorie')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Übersetzung')
                    ->schema([
                        Forms\Components\TextInput::make('translated_category')
                            ->label('Deutsche Kategorie')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('translated_subcategory')
                            ->label('Deutsche Subkategorie')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('petzl_path')
                            ->label('Petzl-Pfad')
                            ->placeholder('z. B. sport/helme')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_reviewed')
                            ->label('Geprüft'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notizen')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_category')
                    ->label('CSV-Kategorie')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('source_subcategory')
                    ->label('CSV-Subkategorie')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('translated_category')
                    ->label('Deutsche Kategorie')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('translated_subcategory')
                    ->label('Deutsche Subkategorie')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('petzl_path')
                    ->label('Petzl-Pfad')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_reviewed')
                    ->label('Geprüft')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aktualisiert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_reviewed')
                    ->label('Geprüft'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktiv'),

                Tables\Filters\Filter::make('missing_translation')
                    ->label('Ohne Übersetzung')
                    ->query(
                        fn($query) => $query->where(function ($query) {
                            $query
                                ->whereNull('translated_category')
                                ->orWhere('translated_category', '')
                                ->orWhereNull('translated_subcategory')
                                ->orWhere('translated_subcategory', '');
                        })
                    ),
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
            'index' => Pages\ListPetzlCategoryMappings::route('/'),
            'create' => Pages\CreatePetzlCategoryMapping::route('/create'),
            'edit' => Pages\EditPetzlCategoryMapping::route('/{record}/edit'),
        ];
    }
}
