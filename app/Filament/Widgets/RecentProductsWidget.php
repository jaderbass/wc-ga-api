<?php

namespace App\Filament\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use App\Models\Product;

class RecentProductsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full'; // über ganze Breite

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                Product::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('productnumber')
                    ->label('Artikelnummer')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('productname')
                    ->label('Produktname')
                    ->limit(40),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Hersteller'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Importiert am')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => route('filament.admin.resources.products.edit', $record))
            ]);
    }
}
