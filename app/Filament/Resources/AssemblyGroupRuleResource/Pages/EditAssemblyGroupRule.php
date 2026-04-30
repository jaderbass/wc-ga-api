<?php

namespace App\Filament\Resources\AssemblyGroupRuleResource\Pages;

use App\Filament\Resources\AssemblyGroupRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssemblyGroupRule extends EditRecord
{
    protected static string $resource = AssemblyGroupRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
