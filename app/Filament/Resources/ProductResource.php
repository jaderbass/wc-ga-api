<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Manufacturer;
use App\Models\Product;
use Filament\Forms\Components\Grid;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ImporterSelector;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('manufacturer_id')
                    ->required()
                    ->relationship('manufacturer', 'manufacturer')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('productnumber')
                    ->maxLength(100)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('eancode')
                    ->maxLength(14)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('skucode')
                    ->maxLength(32)
                    ->columnSpan(3),
                Forms\Components\TextInput::make('productname')
                    ->required()
                    ->maxLength(100)
                    ->columnSpan(3),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpan(6),
                Forms\Components\TextInput::make('shortdescription')
                    ->columnSpan(6),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('regularprice')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('saleprice')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->columnSpan(2),
                Forms\Components\TextInput::make('width')
                    ->numeric()
                    ->integer()
                    ->helperText('Width in mm')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('length')
                    ->numeric()
                    ->integer()
                    ->helperText('Length in mm')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('height')
                    ->numeric()
                    ->integer()
                    ->helperText('Height in mm')
                    ->columnSpan(2),
                Forms\Components\TextInput::make('weight')
                    ->numeric()
                    ->integer()
                    ->helperText('Weight in mm')
                    ->columnSpan(2),
                Forms\Components\Checkbox::make('unit')
                    ->label('Unit')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('unitprice')
                    ->numeric()
                    ->integer()
                    ->columnSpan(2)
                    ->hidden(fn (Get $get): bool => $get('unit')),
                Forms\Components\TextInput::make('pcsperbox')
                    ->numeric()
                    ->integer()
                    ->columnSpan(2)
                    ->hidden(fn (Get $get): bool => $get('unit')),
                Forms\Components\TextInput::make('boxwidth')
                    ->numeric()
                    ->integer()
                    ->helperText('Box width in mm')
                    ->columnSpan(2)
                    ->hidden(fn (Get $get): bool => $get('unit')),
                Forms\Components\TextInput::make('boxlength')
                    ->numeric()
                    ->integer()
                    ->helperText('Box length in mm')
                    ->columnSpan(2)
                    ->hidden(fn (Get $get): bool => $get('unit')),
                Forms\Components\TextInput::make('boxheight')
                    ->numeric()
                    ->integer()
                    ->helperText('Box height in mm')
                    ->columnSpan(2)
                    ->hidden(fn (Get $get): bool => $get('unit')),
            ])
            ->columns(12);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('productnumber')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('eancode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('skucode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('productname')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shortdescription')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('regularprice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('saleprice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('width')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('length')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('height')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unitprice')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pcsperbox')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('boxwidth')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('boxlength')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('boxheight')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mpn')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->searchable()
                    ->sortable(),
                ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->form([
                        Forms\Components\Select::make('manufacturer_id')
                            ->label('Hersteller')
                            ->relationship('manufacturer', 'name')
                            ->required(),
                    ])
                    ->importer(ProductImporter::class)

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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
