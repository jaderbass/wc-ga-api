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
                            ->numeric()
                            ->suffix('Cent')
                            ->helperText('Interner Wert in Cent.'),

                        Forms\Components\TextInput::make('ean')
                            ->label('EAN')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Attribute')
                    ->description('Die Varianten-Attribute bearbeiten wir im nächsten Schritt sauber über Relation oder JSON.')
                    ->schema([
                        Forms\Components\Placeholder::make('attribute_hint')
                            ->label('')
                            ->content('Attribute werden aktuell noch nur angezeigt bzw. später gezielt bearbeitbar gemacht.'),
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
            // 'view' => Pages\ViewProductVariation::route('/{record}'),
            'edit' => Pages\EditProductVariation::route('/{record}/edit'),
        ];
    }
}
