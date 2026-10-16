<?php

namespace App\Filament\Resources\CategoryAssignmentRuleResource\Pages;

use App\Filament\Resources\CategoryAssignmentRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryAssignmentRule extends EditRecord
{
    protected static string $resource = CategoryAssignmentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
