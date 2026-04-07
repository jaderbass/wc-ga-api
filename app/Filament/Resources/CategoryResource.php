<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\CategoryResyncRun;
use Filament\Forms\Form;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Kategorien';

    protected static ?string $modelLabel = 'Kategorie';
    protected static ?string $pluralModelLabel = 'Kategorien';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                FormSection::make('Kategoriedaten')
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                    if (filled($state) && blank($get('slug'))) {
                                        $set('slug', Str::slug($state));
                                    }
                                })
                                ->columnSpan(6),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->helperText('Wird beim ersten Eintragen automatisch aus dem Namen vorgeschlagen.')
                                ->columnSpan(6),

                            Select::make('parent_id')
                                ->label('Oberkategorie')
                                ->relationship(
                                    'parent',
                                    'name',
                                    fn($query, ?Category $record) => $query
                                        ->when($record, fn($q) => $q->whereKeyNot($record->getKey()))
                                        ->orderBy('name')
                                )
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->placeholder('Keine Oberkategorie')
                                ->columnSpan(6),
                        ]),
                    ])
                    ->collapsible(),

                FormSection::make('Automatisierungsregeln')
                    ->description('Keywords für die automatische Kategorisierung. Die Reihenfolge bestimmt die Abarbeitung.')
                    ->schema([
                        Repeater::make('rules')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): ?array {
                                $keyword = mb_strtolower(trim((string) ($data['keyword'] ?? '')), 'UTF-8');
                                $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

                                if ($keyword === '') {
                                    return null;
                                }

                                return [
                                    ...$data,
                                    'keyword' => $keyword,
                                ];
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): ?array {
                                $keyword = mb_strtolower(trim((string) ($data['keyword'] ?? '')), 'UTF-8');
                                $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

                                if ($keyword === '') {
                                    return null;
                                }

                                return [
                                    ...$data,
                                    'keyword' => $keyword,
                                ];
                            })
                            ->label('Regeln')
                            ->schema([
                                Grid::make(12)->schema([
                                    TextInput::make('keyword')
                                        ->label('Keyword')
                                        // ->required()
                                        ->maxLength(255)
                                        ->placeholder('z. B. karabiner, helm oder gurt')
                                        ->dehydrateStateUsing(function (?string $state): string {
                                            $value = mb_strtolower(trim((string) $state), 'UTF-8');
                                            $value = preg_replace('/\s+/', ' ', $value) ?? $value;

                                            return $value;
                                        })
                                        ->columnSpanFull(),
                                ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Keyword hinzufügen')
                            ->reorderable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(function (array $state): ?string {
                                $keyword = trim((string) ($state['keyword'] ?? ''));

                                return $keyword !== '' ? $keyword : 'Neues Keyword';
                            }),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Oberkategorie')
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Produkte')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rules_count')
                    ->label('Regeln')
                    ->counts('rules')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Geändert')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label('Löschen')
                    ->requiresConfirmation()
                    ->visible(function (Category $record): bool {
                        if ($record->name === 'Allgemein') {
                            return false;
                        }

                        return ! $record->products()->exists();
                    }),


                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make()->label('Löschen'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
