<?php

namespace App\Filament\Resources\AssemblyGroupRuleResource\Pages;

use App\Filament\Resources\AssemblyGroupRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyGroupRules extends ListRecords
{
    protected static string $resource = AssemblyGroupRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
