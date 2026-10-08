<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryAssignmentRuleResource\Pages;
use App\Models\Category;
use App\Models\CategoryAssignmentRule;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Backend-Seite "Hersteller-Zuordnung": Herstellerkategorie bzw. Stichwort → Shop-Kategorien.
 */
class CategoryAssignmentRuleResource extends Resource
{
    protected static ?string $model = CategoryAssignmentRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Hersteller-Zuordnung';

    protected static ?string $modelLabel = 'Zuordnung';

    protected static ?string $pluralModelLabel = 'Hersteller-Zuordnung';

    protected static ?string $slug = 'hersteller-zuordnung';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Was wird zugeordnet?')
                ->schema([
                    Forms\Components\Select::make('manufacturer_id')
                        ->label('Hersteller')
                        ->relationship('manufacturer', 'manufacturer', fn (Builder $query) => $query->orderBy('manufacturer'))
                        ->searchable()
                        ->preload()
                        ->placeholder('Alle Hersteller')
                        ->helperText('Leer = Stichwort gilt für alle Hersteller.'),

                    Forms\Components\TextInput::make('source_category')
                        ->label('Herstellerkategorie')
                        ->maxLength(255)
                        ->helperText('So wie in der Herstellerliste, z. B. "VERBINDUNGSMITTEL". Bei einem Stichwort optional: dann gilt es nur innerhalb dieser Herstellerkategorie.'),

                    Forms\Components\TextInput::make('keyword')
                        ->label('Stichwort im Produktnamen')
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->requiredWithout('source_category')
                        ->helperText('Mehrere mit | trennen. Trifft am Wortanfang ("ring" trifft "Ring", nicht "Spring"); mit * davor auch mitten im Wort ("*rolle" trifft "Umlenkrolle").'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Reihenfolge')
                        ->numeric()
                        ->default(0)
                        ->visible(fn (Get $get): bool => filled($get('keyword')))
                        ->helperText('Kleinere Zahl wird zuerst geprüft. Die erste passende Regel gewinnt.'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Ergebnis')
                ->schema([
                    Forms\Components\Select::make('categories')
                        ->label('Shop-Kategorien')
                        ->relationship('categories', 'name', fn (Builder $query) => $query->with('parent')->orderBy('name'))
                        ->getOptionLabelFromRecordUsing(fn (Category $record): string => self::categoryPath($record))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Produktkategorie und ggf. Einsatzgebiete, z. B. "Segel-Zubehör" und "Segeln".'),

                    Forms\Components\Toggle::make('exclude')
                        ->label('Nicht importieren')
                        ->helperText('Produkte bekommen keine Kategorie und werden nicht in den Shop übernommen.'),

                    Forms\Components\Toggle::make('is_reviewed')
                        ->label('Geprüft'),

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
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['manufacturer', 'categories.parent'])
                ->addSelect([
                    'products_count' => Product::query()
                        ->selectRaw('count(*)')
                        ->whereColumn('products.manufacturer_id', 'category_assignment_rules.manufacturer_id')
                        ->whereColumn('products.source_category', 'category_assignment_rules.source_category'),
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('manufacturer.manufacturer')
                    ->label('Hersteller')
                    ->placeholder('alle')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('source_category')
                    ->label('Herstellerkategorie')
                    ->placeholder('—')
                    ->wrap()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('keyword')
                    ->label('Stichwort')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('categories')
                    ->label('→ Shop-Kategorien')
                    ->state(fn (CategoryAssignmentRule $record): array => $record->exclude
                        ? ['nicht importieren']
                        : $record->categories->map(fn (Category $category): string => self::categoryPath($category))->all())
                    ->badge()
                    ->color(fn (CategoryAssignmentRule $record): string => $record->exclude ? 'danger' : 'primary')
                    ->placeholder('offen'),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Produkte')
                    ->state(fn (CategoryAssignmentRule $record): ?int => $record->isKeywordRule() ? null : (int) $record->products_count)
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Reihenfolge')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_reviewed')
                    ->label('Geprüft')
                    ->boolean(),
            ])
            ->defaultSort('source_category')
            ->filters([
                Tables\Filters\SelectFilter::make('manufacturer_id')
                    ->label('Hersteller')
                    ->relationship('manufacturer', 'manufacturer')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('type')
                    ->label('Art')
                    ->trueLabel('Stichwort-Regeln')
                    ->falseLabel('Herstellerkategorien')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('keyword')->where('keyword', '!=', ''),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('keyword')->orWhere('keyword', '')),
                    ),

                Tables\Filters\Filter::make('open')
                    ->label('Nur offene (ohne Kategorie)')
                    ->query(fn (Builder $query) => $query->where('exclude', false)->whereDoesntHave('categories')),

                Tables\Filters\TernaryFilter::make('is_reviewed')
                    ->label('Geprüft'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function categoryPath(Category $category): string
    {
        $names = [$category->name];
        $parent = $category->parent;
        $guard = 0;

        while ($parent !== null && $guard++ < 10) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
        }

        // "Produkte Übersicht > Produkte" ist bei jedem Pfad gleich und wird weggelassen
        $names = array_values(array_diff($names, ['Produkte Übersicht', 'Produkte']));

        return implode(' > ', $names === [] ? [$category->name] : $names);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoryAssignmentRules::route('/'),
            'create' => Pages\CreateCategoryAssignmentRule::route('/create'),
            'edit' => Pages\EditCategoryAssignmentRule::route('/{record}/edit'),
        ];
    }
}
