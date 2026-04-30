<?php

namespace App\Filament\Resources\AssemblyGroupRuleResource\Pages;

use App\Filament\Resources\AssemblyGroupRuleResource;
use App\Models\AssemblyGroupResyncRun;
use App\Jobs\ResyncAssemblyGroupsJob;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListAssemblyGroupRules extends ListRecords
{
    protected static string $resource = AssemblyGroupRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('resyncAssemblyGroups')
                ->label('Baugruppen neu berechnen')
                ->icon('heroicon-m-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $run = AssemblyGroupResyncRun::create([
                        'status' => 'queued',
                    ]);

                    ResyncAssemblyGroupsJob::dispatch($run->id);

                    Notification::make()
                        ->title('Neuzuordnung gestartet')
                        ->body('Die Baugruppen werden im Hintergrund neu berechnet.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\AssemblyGroupResyncStatusWidget::class,
        ];
    }
}
