<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\CategoryResyncRun;
use Filament\Actions;
use Filament\Notifications\Notification;
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

    protected static string $view = 'filament.resources.category-resource.pages.list-categories';

    /**
     * Polling-Intervall für automatische Livewire-Aktualisierung.
     */
    protected ?string $pollingInterval = '5s';

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

    /**
     * Liefert den zuletzt bekannten Resync-Lauf.
     */
    public function getLatestResyncRunProperty(): ?CategoryResyncRun
    {
        return CategoryResyncRun::query()
            ->latest('id')
            ->first();
    }

    /**
     * Liefert einen kompakten Status-Text für die Ansicht.
     */
    public function getResyncStatusTextProperty(): ?string
    {
        $run = $this->latestResyncRun;

        if (! $run) {
            return null;
        }

        return match ($run->status) {
            'queued' => 'Die Neuzuordnung wurde eingeplant.',
            'running' => sprintf(
                'Die Neuzuordnung läuft: %d / %d Produkte verarbeitet.',
                $run->processed,
                $run->total
            ),
            'finished' => sprintf(
                'Die Neuzuordnung wurde abgeschlossen: %d / %d Produkte verarbeitet.',
                $run->processed,
                $run->total
            ),
            'failed' => 'Die Neuzuordnung ist fehlgeschlagen.',
            default => null,
        };
    }
}
