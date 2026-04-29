<?php

namespace App\Filament\Resources\ProductVariationResource\Pages;

use App\Filament\Resources\ProductVariationResource;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductVariation extends EditRecord
{
    protected static string $resource = ProductVariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToProduct')
                ->label('Zurück zum Produkt')
                ->icon('heroicon-m-arrow-left')
                ->url(fn() => ProductResource::getUrl('edit', [
                    'record' => $this->record->product_id,
                ])),
        ];
    }
}
