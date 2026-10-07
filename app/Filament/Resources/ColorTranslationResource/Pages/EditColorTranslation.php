<?php

namespace App\Filament\Resources\ColorTranslationResource\Pages;

use App\Filament\Resources\ColorTranslationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditColorTranslation extends EditRecord
{
    protected static string $resource = ColorTranslationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
