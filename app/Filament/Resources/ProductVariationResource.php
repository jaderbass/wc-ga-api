<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductVariationResource\Pages;
use App\Filament\Resources\ProductVariationResource\RelationManagers;
use App\Models\ProductVariation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use App\Services\ProductNaming\VariationDisplayNameResolver;

/**
 * Filament-Resource für Produktvarianten.
 *
 * Die Resource ist bewusst nicht in der Navigation registriert. Varianten werden
 * aus der Produktansicht heraus bearbeitet, damit der fachliche Kontext zum
 * Parent-Produkt erhalten bleibt.
 */
class ProductVariationResource extends Resource
{
    protected static ?string $model = ProductVariation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'Variante';
    protected static ?string $pluralModelLabel = 'Varianten';

    /**
     * Definiert das Formular zur Bearbeitung einer Produktvariante.
     *
     * Der automatisch berechnete Variantenname wird nur angezeigt. Bearbeitbar sind
     * variantenspezifische Felder wie Artikelnummer, Preis und JSON-Attribute.
     *
     * @param \Filament\Forms\Form $form
     * @return \Filament\Forms\Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Variantendaten')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('Artikelnummer')
                            ->maxLength(255),

                        Forms\Components\Placeholder::make('resolved_variant_name')
                            ->label('Automatisch berechneter Variantenname')
                            ->content(
                                fn(ProductVariation $record): string =>
                                app(VariationDisplayNameResolver::class)->resolve($record)
                            ),

                        Forms\Components\TextInput::make('manufacturer_price_cents')
                            ->label('Herstellerpreis')
                            ->suffix('€')
                            ->formatStateUsing(
                                fn($state) =>
                                filled($state)
                                    ? number_format(((int) $state) / 100, 2, ',', '')
                                    : null
                            )
                            ->dehydrateStateUsing(
                                fn($state) =>
                                filled($state)
                                    ? (int) round(
                                        (float) str_replace(',', '.', $state) * 100
                                    )
                                    : null
                            )
                            ->numeric(),

                        Forms\Components\TextInput::make('ean')
                            ->label('EAN')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Attribute')
                    ->schema([
                        Forms\Components\KeyValue::make('attributes_json')
                            ->label('Attribute')
                            ->keyLabel('Attribut')
                            ->valueLabel('Wert')
                            ->addActionLabel('Attribut hinzufügen')
                            ->reorderable()
                            ->helperText('Diese Attribute werden direkt an der Variante gespeichert.'),
                    ]),
            ]);
    }

    /**
     * Definiert die technische Tabellenansicht der Varianten-Resource.
     *
     * Die Seite wird nicht aktiv im UI genutzt, muss aber für Filament-Routen
     * vorhanden bleiben.
     *
     * @param \Filament\Tables\Table $table
     * @return \Filament\Tables\Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
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

    /**
     * Definiert die Beziehungen der Varianten-Resource.
     *
     * @return array<int, \Filament\Resources\RelationManagers\RelationManager>
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Definiert die verfügbaren Seiten für die Varianten-Resource.
     *
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariations::route('/'),
            'edit' => Pages\EditProductVariation::route('/{record}/edit'),
        ];
    }

    /**
     * Gibt den deutschen Seitentitel aus.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Variante bearbeiten';
    }
}
