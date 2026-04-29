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

class ProductVariationResource extends Resource
{
    protected static ?string $model = ProductVariation::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

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
                            ->label('Variantenname')
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductVariations::route('/'),
            'edit' => Pages\EditProductVariation::route('/{record}/edit'),
        ];
    }
}
