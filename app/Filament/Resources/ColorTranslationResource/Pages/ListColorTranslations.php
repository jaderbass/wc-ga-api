<?php

namespace App\Filament\Resources\ColorTranslationResource\Pages;

use App\Filament\Resources\ColorTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListColorTranslations extends ListRecords
{
    protected static string $resource = ColorTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
