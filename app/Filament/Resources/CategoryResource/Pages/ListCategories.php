<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * Listenansicht für Kategorien.
 *
 * Zeigt den Status des letzten Kategorien-Resyncs an und aktualisiert
 * sich automatisch in festen Abständen.
 */
class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    /**
     * Liefert die Header-Actions der Listenansicht.
     *
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
