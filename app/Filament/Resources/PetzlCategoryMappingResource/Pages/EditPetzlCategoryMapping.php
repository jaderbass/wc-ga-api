<?php

namespace App\Filament\Resources\PetzlCategoryMappingResource\Pages;

use App\Filament\Resources\PetzlCategoryMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPetzlCategoryMapping extends EditRecord
{
    protected static string $resource = PetzlCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
