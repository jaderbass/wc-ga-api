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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required(),

                Select::make('field')
                    ->options([
                        'product_name' => 'Produktname',
                    ])
                    ->required(),

                Select::make('operator')
                    ->options([
                        'contains' => 'enthält',
                    ])
                    ->required(),

                TextInput::make('value')->required(),

                TextInput::make('assembly_group')
                    ->numeric()
                    ->required(),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->default(true),
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
            'index' => Pages\ListAssemblyGroupRules::route('/'),
            'create' => Pages\CreateAssemblyGroupRule::route('/create'),
            'edit' => Pages\EditAssemblyGroupRule::route('/{record}/edit'),
        ];
    }
}
