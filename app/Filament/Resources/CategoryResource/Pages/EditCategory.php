<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Jobs\ResyncProductCategoriesJob;
use App\Models\Category;
use App\Models\CategoryResyncRun;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Bearbeitungsseite für Kategorien.
 *
 * Diese Seite blendet die Delete-Action aus, wenn eine Kategorie
 * fachlich nicht gelöscht werden darf.
 *
 * Regeln:
 * - Die Standardkategorie "Allgemein" darf nicht gelöscht werden.
 * - Kategorien mit bestehender Produktzuordnung dürfen nicht gelöscht werden.
 */
class EditCategory extends EditRecord
{
    /**
     * Zugehörige Filament-Resource.
     *
     * @var class-string<CategoryResource>
     */
    protected static string $resource = CategoryResource::class;

    /**
     * Liefert die Header-Actions der Bearbeitungsseite.
     *
     * @return array<int, Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('resyncCategories')
                ->label('Produkte neu zuordnen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Produkte neu zuordnen')
                ->modalDescription('Alle Produkte werden anhand der aktuellen Kategorie-Regeln neu synchronisiert. Manuell gesetzte Kategorien bleiben erhalten.')
                ->action(function (): void {
                    $run = \App\Models\CategoryResyncRun::create([
                        'status' => 'queued',
                        'processed' => 0,
                        'total' => 0,
                        'message' => 'Neuzuordnung wurde zur Verarbeitung eingeplant.',
                    ]);

                    \App\Jobs\ResyncProductCategoriesJob::dispatch(
                        productId: null,
                        manufacturerId: null,
                        chunkSize: 200,
                        runId: $run->id,
                    )->onQueue('imports');

                    // \Filament\Notifications\Notification::make()
                    //     ->title('Neuzuordnung gestartet')
                    //     ->body('Die Produkte werden im Hintergrund anhand der aktuellen Kategorie-Regeln neu zugeordnet.')
                    //     ->success()
                    //     ->send();
                }),
        ];

        if ($this->canDeleteRecord()) {
            $actions[] = Actions\DeleteAction::make();
        }

        return $actions;
    }

    /**
     * Prüft, ob die aktuelle Kategorie gelöscht werden darf.
     *
     * Nicht löschbar sind:
     * - die Kategorie "Allgemein"
     * - Kategorien, die bereits Produkten zugewiesen sind
     *
     * @return bool
     */
    protected function canDeleteRecord(): bool
    {
        /** @var Category $record */
        $record = $this->getRecord();

        if ($record->name === 'Allgemein') {
            return false;
        }

        return ! $record->products()->exists();
    }

    /**
     * Normalisiert und dedupliziert die Regeln vor dem Speichern.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['rules'] = $this->normalizeRules($data['rules'] ?? []);

        return $data;
    }

    /**
     * Bereinigt Regel-Daten für das Formular.
     *
     * - trimmt Keywords
     * - normalisiert Leerzeichen
     * - wandelt in Kleinbuchstaben um
     * - entfernt leere und doppelte Keywords
     * - setzt die Sortierung anhand der Reihenfolge im Repeater
     *
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRules(array $rules): array
    {
        $seen = [];
        $result = [];

        foreach ($rules as $rule) {
            $keyword = mb_strtolower(trim((string) ($rule['keyword'] ?? '')), 'UTF-8');
            $keyword = preg_replace('/\s+/', ' ', $keyword) ?? $keyword;

            if ($keyword === '' || isset($seen[$keyword])) {
                continue;
            }

            $seen[$keyword] = true;

            $result[] = [
                'keyword' => $keyword,
                'sort_order' => count($result),
            ];
        }

        return $result;
    }
}
