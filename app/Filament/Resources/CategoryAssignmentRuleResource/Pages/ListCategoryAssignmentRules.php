<?php

namespace App\Filament\Resources\CategoryAssignmentRuleResource\Pages;

use App\Filament\Resources\CategoryAssignmentRuleResource;
use App\Jobs\ResyncProductCategoriesJob;
use App\Models\CategoryResyncRun;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCategoryAssignmentRules extends ListRecords
{
    protected static string $resource = CategoryAssignmentRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resyncCategories')
                ->label('Kategorien neu zuordnen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Kategorien neu zuordnen')
                ->modalDescription('Alle Produkte werden anhand der Hersteller-Zuordnung und der Kategorie-Regeln neu zugeordnet. Manuell gesetzte Kategorien bleiben erhalten. Es wird nichts in den Shop übertragen.')
                ->action(function (): void {
                    $run = CategoryResyncRun::create([
                        'status' => 'queued',
                        'processed' => 0,
                        'total' => 0,
                        'message' => 'Neuzuordnung wurde zur Verarbeitung eingeplant.',
                    ]);

                    ResyncProductCategoriesJob::dispatch(
                        productId: null,
                        manufacturerId: null,
                        chunkSize: 200,
                        runId: $run->id,
                    )->onQueue('imports');

                    Notification::make()
                        ->title('Neuzuordnung eingeplant')
                        ->success()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
