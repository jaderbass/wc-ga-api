<?php

namespace App\Filament\Resources\ProductVariationResource\Pages;

use App\Filament\Resources\ProductVariationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Technische Listen-Seite für Produktvarianten.
 *
 * Diese Seite wird von Filament intern für Resource-Routen benötigt, soll aber
 * nicht aktiv im UI genutzt werden. Die Varianten werden stattdessen über die
 * Produkt-Detailseite bearbeitet.
 */
class ListProductVariations extends ListRecords
{
    protected static string $resource = ProductVariationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Leitet direkte Aufrufe der technischen Varianten-Liste zur Produktübersicht um.
     *
     * @return void
     */
    public function mount(): void
    {
        $this->redirect(
            route('filament.admin.resources.products.index')
        );
    }
}
