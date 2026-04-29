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
                    ->schema([
                        Forms\Components\Placeholder::make('attributes_display')
                            ->label('Attribut-Kombination')
                            ->content(function (ProductVariation $record): string {
                                $record->loadMissing(['attributeValues.attribute']);

                                if ($record->attributeValues->isNotEmpty()) {
                                    return $record->attributeValues
                                        ->map(function ($attributeValue): string {
                                            $attributeName = $attributeValue->attribute?->name ?? 'Attribut';
                                            $value = $attributeValue->value ?? '—';

                                            return "{$attributeName}: {$value}";
                                        })
                                        ->implode(' | ');
                                }

                                if (is_array($record->attributes_json) && filled($record->attributes_json)) {
                                    return collect($record->attributes_json)
                                        ->map(function ($value, $key): string {
                                            $label = preg_replace('/^Attribute Group:\s*/i', '', (string) $key);
                                            $label = trim($label);

                                            return "{$label}: {$value}";
                                        })
                                        ->implode(' | ');
                                }

                                return 'Keine Attribute hinterlegt.';
                            }),
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
