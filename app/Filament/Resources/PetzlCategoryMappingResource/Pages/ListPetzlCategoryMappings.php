<?php

namespace App\Filament\Resources\PetzlCategoryMappingResource\Pages;

use App\Filament\Resources\PetzlCategoryMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPetzlCategoryMappings extends ListRecords
{
    protected static string $resource = PetzlCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
