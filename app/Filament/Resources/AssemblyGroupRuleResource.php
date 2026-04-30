<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssemblyGroupRuleResource\Pages;
use App\Filament\Resources\AssemblyGroupRuleResource\RelationManagers;
use App\Models\AssemblyGroupRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AssemblyGroupRuleResource extends Resource
{
    protected static ?string $model = AssemblyGroupRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Baugruppen-Regeln';

    protected static ?string $modelLabel = 'Baugruppen-Regel';

    protected static ?string $pluralModelLabel = 'Baugruppen-Regeln';

    protected static ?string $navigationGroup = 'Produkte';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->default(fn($get) => 'Produktname enthält "' . $get('value') . '"'),

                Select::make('field')
                    ->label('Feld')
                    ->options([
                        'product_name' => 'Produktname',
                    ])
                    ->required(),

                Select::make('operator')
                    ->label('Operator')
                    ->options([
                        'contains' => 'enthält',
                    ])
                    ->required(),

                TextInput::make('value')
                    ->label('Suchwert')
                    ->required()
                    ->minLength(2),

                TextInput::make('assembly_group')
                    ->label('Baugruppe')
                    ->numeric()
                    ->required(),

                TextInput::make('sort_order')
                    ->label('Reihenfolge')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Regel')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('condition')
                    ->label('Bedingung')
                    ->state(function ($record): string {
                        return 'Produktname enthält "' . $record->value . '"';
                    }),

                Tables\Columns\TextColumn::make('assembly_group')
                    ->label('Baugruppe')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Reihenfolge')
                    ->sortable(),
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
            'index' => Pages\ListAssemblyGroupRules::route('/'),
            'create' => Pages\CreateAssemblyGroupRule::route('/create'),
            'edit' => Pages\EditAssemblyGroupRule::route('/{record}/edit'),
        ];
    }
}
