<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColorTranslationResource\Pages;
use App\Models\ColorTranslation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ColorTranslationResource extends Resource
{
    protected static ?string $model = ColorTranslation::class;

    protected static ?string $navigationGroup = 'Produkte';

    protected static ?string $navigationLabel = 'Farb-Übersetzungen';

    protected static ?string $modelLabel = 'Farbe';

    protected static ?string $pluralModelLabel = 'Farben';

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Farbe')
                ->schema([
                    Forms\Components\TextInput::make('source_value')
                        ->label('Originalwert (Import)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('source_slug', \Illuminate\Support\Str::slug((string) $state))),

                    Forms\Components\TextInput::make('source_slug')
                        ->label('Kennung')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Forms\Components\TextInput::make('translated_value')
                        ->label('Deutsche Farbe')
                        ->helperText('Leer lassen = Originalwert wird angezeigt. Sonderfarben (z. B. Royal Blue) einfach 1:1 eintragen.')
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Status')
                ->schema([
                    Forms\Components\Toggle::make('is_reviewed')->label('Geprüft'),
                    Forms\Components\Toggle::make('is_active')->label('Aktiv')->default(true),
                    Forms\Components\Textarea::make('notes')->label('Notizen')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('source_value')->label('Original')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('translated_value')->label('Deutsche Farbe')->searchable()->sortable()->placeholder('—'),
                Tables\Columns\IconColumn::make('is_auto')->label('Automatisch')->boolean(),
                Tables\Columns\IconColumn::make('is_reviewed')->label('Geprüft')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktiv')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->label('Aktualisiert')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('source_value')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_reviewed')->label('Geprüft'),
                Tables\Filters\TernaryFilter::make('is_auto')->label('Automatisch übersetzt'),
                Tables\Filters\Filter::make('missing_translation')
                    ->label('Ohne Übersetzung')
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('translated_value')->orWhere('translated_value', ''))),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_reviewed')
                        ->label('Als geprüft markieren')
                        ->action(fn ($records) => $records->each->update(['is_reviewed' => true])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListColorTranslations::route('/'),
            'create' => Pages\CreateColorTranslation::route('/create'),
            'edit' => Pages\EditColorTranslation::route('/{record}/edit'),
        ];
    }
}
